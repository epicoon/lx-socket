#lx:module lx.socket.WebSocketClient;

#lx:require ChannelMate;
#lx:require Message;
#lx:require EventListener;
#lx:require ConnectionEvent;
#lx:require ChannelEvent;


class RequestHandler {
    constructor(socket, key) {
        this.socket = socket;
        this.key = key;
    }
    
    then(callback) {
        this.socket.__qBuffer[this.key] = callback;
    }
}


#lx:namespace lx.socket;
class WebSocketClient {
    #lx:const
        STATUS_NEW = 1,
        STATUS_IN_CONNECTING = 2,
        STATUS_CONNECTED = 3,
        STATUS_CLOSED = 4,
        STATUS_DISCONNECTED = 5,
        STATUS_WAITING_FOR_RECONNECTING = 6;

    /**
     * @param {Object} config [[protocol, port, url, channel, handlers]]
     */
    constructor(config) {
        this._port = config.port || null;
        this._channel = config.channel || null;
        this._urlPrefix = (config.protocol || 'ws') + '://' + document.location.hostname;
        this._route = config.url ? '/' + config.url : '';

        this._channelOpenData = null;
        this._channelAuthData = null;

        this._socket = null;
        this._status = self::STATUS_NEW;
        this._isReadyForClose = false;
        this._id = null;
        this._reconnectionAllowed = false;
        this._channelMates = {};
        this._channelData = {};
        this._errors = [];

        this._onBeforeSend = [];
        this._onConnected = [];
        this._onClientJoin = [];
        this._onAddOpenData = [];
        this._onClientDisconnected = [];
        this._onClientReconnected = [];
        this._onClientLeave = [];
        this._onEvent = [];
        this._onopen = [];
        this._onmessage = [];
        this._onclose = [];
        this._onError = [];

        this.__qCounter = 0;
        this.__qBuffer = {};

        if (config.handlers) {
            let handlers = config.handlers,
                methods = ['onBeforeSend', 'onConnected', 'onClientJoin', 'onAddOpenData',
                    'onClientDisconnected', 'onClientReconnected', 'onClientLeave',
                    'onChannelEvent', 'onOpen', 'onMessage', 'onClose', 'onError'
                ];
            for (let handlerName in handlers) {
                if (!methods.includes(handlerName)) continue;
                this[handlerName](handlers[handlerName]);
            }
        }
    }

    setPort(port) {
        this._port = port;
    }

    setChannel(channel) {
        this._channel = channel;
    }

    getUrl() {
        if (this._channel === null) return false;

        if (this._port === null)
            return this._urlPrefix + this._route + '/' + this._channel;

        return this._urlPrefix + ':' + this._port + this._route + '/' + this._channel;
    }

    getId() {
        return this._id;
    }

    getChannelOpenData() {
        return this._channelOpenData.lxClone();
    }

    getChannelMates() {
        return this._channelMates;
    }

    getChannelMate(id) {
        if (id in this._channelMates)
            return this._channelMates[id];
        return null;
    }

    getLocalMate() {
        return this._channelMates[this._id];
    }

    getChannelData() {
        return this._channelData.lxClone();
    }

    isConnected() {
        return this._status === self::STATUS_CONNECTED;
    }

    hasErrors() {
        return this._errors.len;
    }

    getErrors() {
        return this._errors;
    }

    connect(channelOpenData = null, authData = null) {
        if (this.isConnected()) return;

        let url = this.getUrl();
        if (url === false) {
            let msg = 'Connection is unavailable. You have to define port and channel. Current port: ';
            msg += (this._port === null)
                ? 'undefined'
                : this._port;
            msg += '. Current channel: ';
            msg += (this._channel === null)
                ? 'undefined'
                : this._channel;
            msg += '.';
            this._errors.push(msg);
            return;
        }

        this._channelOpenData = channelOpenData;
        this._channelAuthData = authData;
        if (window.MozWebSocket) this._socket = new MozWebSocket(url);
        else if (window.WebSocket) this._socket = new WebSocket(url);
        this._socket.binaryType = 'blob';
        this._status = self::STATUS_IN_CONNECTING;

        __setSocketHandlers(this);
    }

    reconnect() {
        if (this._status !== self::STATUS_CLOSED && this._status !== self::STATUS_DISCONNECTED) return;
        delete this._channelOpenData.id;
        this.connect(this._channelOpenData, this._channelAuthData);
    }

    addOpenData(data) {
        __sendData(this, {__lxws_action__:'addOpenData', data});
    }
    
    close() {
        if (this._reconnectionAllowed && this._channel) {
            let map = lx.app.storage.get('lxsocket') || {};
            if (map.reconnect && (this._channel in map.reconnect) && map.reconnect[this._channel] == this._id) {
                delete map.reconnect[this._channel];
                lx.app.storage.set('lxsocket', map);
            }
        }

        if (!this.isConnected()) return;
        __sendData(this, {__lxws_action__:'close'});
    }

    break() {
        if (!this.isConnected()) return;
        __sendData(this, {__lxws_action__:'break'});
    }

    send(data, receivers = null, privateMode = false) {
        let msg = __prepareMessageForSend(data, receivers, privateMode);
        __sendData(this, msg);
    }

    trigger(eventName, data = {}, receivers = null, privateMode = false) {
        let msg = __prepareMessageForSend(data, receivers, privateMode);
        msg.__metaData__.__event__ = eventName;
        __sendData(this, msg);
    }

    request(route, data) {
        let msg = __prepareMessageForSend(data, [this._id], true),
            key = __getRequestKey(this);
        msg.__metaData__.__request__ = {route, key};
        let handler = new RequestHandler(this, key);
        __sendData(this, msg);
        return handler;
    }

    onPromisedConnection(callback) {
        if (this.isConnected()) {
            callback();
            return;
        }
        this.onConnected(callback);
    }

    onBeforeSend(callback) {
        this._onBeforeSend.push(callback);
    }

    onConnected(callback) {
        this._onConnected.push(callback);
    }

    onClientJoin(callback) {
        this._onClientJoin.push(callback);
    }

    onAddOpenData(callback) {
        this._onAddOpenData.push(callback);
    }

    onClientDisconnected(callback) {
        this._onClientDisconnected.push(callback);
    }

    onClientReconnected(callback) {
        this._onClientReconnected.push(callback);
    }

    onClientLeave(callback) {
        this._onClientLeave.push(callback);
    }

    onChannelEvent(callback) {
        if (callback instanceof lx.socket.EventListener)
            callback.setSocket(this);
        this._onEvent.push(callback);
    }

    onOpen(callback) {
        this._onopen.push(callback);
    }

    onMessage(callback) {
        this._onmessage.push(callback);
    }

    onClose(callback) {
        this._onclose.push(callback);
    }

    onError(callback) {
        this._onError.push(callback);
    }

    static dropReconnectionsData(list) {
        let lxSocketData = lx.app.storage.get('lxsocket') || {};
        for (let i in list) {
            let name = list[i];
            if (name in lxSocketData.reconnect)
                delete lxSocketData.reconnect[name];
        }
        lx.app.storage.set('lxsocket', lxSocketData);
    }

    static filterReconnectionsData(list) {
        let lxSocketData = lx.app.storage.get('lxsocket') || {},
            newReconnect = {};
        for (let i in list) {
            let name = list[i];
            if (name in lxSocketData.reconnect)
                newReconnect[name] = lxSocketData.reconnect[name];
        }
        lxSocketData.reconnect = newReconnect;
        lx.app.storage.set('lxsocket', lxSocketData);
    }
}


/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * PRIVATE
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

function __setSocketHandlers(self) {
    __setSocketHandlerOnOpen(self);
    __setSocketHandlerOnMessage(self);
    __setSocketHandlerOnClose(self);
    __setSocketHandlerOnError(self);
}

function __setSocketHandlerOnOpen(self) {
    if (self._socket === null) return;
    self._socket.onopen = function() {
        self._status = lx.socket.WebSocketClient.STATUS_CONNECTED;
        __runHandlers(self, 'open', self._onopen);
    }
}

function __setSocketHandlerOnMessage(self) {
    if (self._socket === null) return;
    self._socket.onmessage =(e)=>{
        let msg = JSON.parse(e.data);

        if (self._id === null) {
            __onHandshake(self, msg);
            return;
        }

        if (msg.__lxws_event__) {
            let mate;
            switch (msg.__lxws_event__) {
                case 'oldConnectionIdNotFound':
                    __sendConnectionData(self);
                    break;
                case 'clientJoin':
                    mate = new lx.socket.ChannelMate(self, msg.client.id, msg.client);
                    self._channelMates[msg.client.id] = mate;
                    if (mate.isLocal())
                        __runHandlers(self, 'connected', self._onConnected);
                    else
                        __runHandlers(self, 'clientJoin', self._onClientJoin, {mate});
                    break;
                case 'clientReconnected':
                    mate = new lx.socket.ChannelMate(self, msg.client.id, msg.client);
                    self._channelMates[msg.client.id] = mate;
                    __runHandlers(self, 'clientReconnected', self._onClientReconnected, {
                        mate,
                        oldConnectionId: msg.oldConnectionId
                    });
                    break;
                case 'clientDisconnected':
                    mate = self._channelMates[msg.client.id];
                    delete self._channelMates[msg.client.id];
                    __runHandlers(self, 'clientDisconnected', self._onClientDisconnected, {mate});
                    break;
                case 'clientAddOpenData':
                    mate = self.getChannelMate(msg.connectionId);
                    let oldData = {};
                    for (let key in msg.data) {
                        oldData[key] = mate[key];
                        mate.addParam(key, msg.data[key]);
                    }
                    __runHandlers(self, 'addOpenData', self._onAddOpenData, {
                        mate,
                        oldData,
                        newData: msg.data
                    });
                    break;
                case 'clientLeave':
                    mate = self._channelMates[msg.client.id];
                    delete self._channelMates[msg.client.id];
                    __runHandlers(self, 'clientLeave', self._onClientLeave, {mate});
                    break;
                case 'close':
                    self._isReadyForClose = true;
                    self._socket.close();
                    break;
                case 'break':
                    self._socket.close();
                    break;
            }
            return;
        }

        if (msg.__event__ && self._onEvent.len) {
            __processEvent(self, msg);
            return;
        }

        if (msg.__multipleEvents__ && self._onEvent.len) {
            msg.__multipleEvents__.forEach(e=>__processEvent(self, e));
            return;
        }

        if (msg.__response__) {
            if (msg.__response__ in self.__qBuffer) {
                let f = self.__qBuffer[msg.__response__];
                delete self.__qBuffer[msg.__response__];
                f(msg.data);
            }
            return;
        }

        if (msg.__dump__) {
            lx.alert(msg.__dump__)
            return;
        }

        __runHandlers(self, new lx.socket.Message(self, msg), self._onmessage);
    };
}

function __onHandshake(self, msg) {
    self._id = msg.id;
    if (self._channelOpenData === null)
        self._channelOpenData = {};
    self._channelOpenData.id = self._id;
    self._channelMates = {};
    for (let mateId in msg.connections) {
        self._channelMates[mateId] = new lx.socket.ChannelMate(self, mateId, msg.connections[mateId]);
    }
    self._channelData = lx.isArray(msg.channelData) ? {} : msg.channelData;

    if (msg.reconnectionAllowed) {
        self._reconnectionAllowed = true;
        self._reconnectionStep = 0;
        self._reconnectionNextStep = 1;

        let channelKey = self._channel,
            lxSocketData = lx.app.storage.get('lxsocket') || {};
        if (!lxSocketData.reconnect) lxSocketData.reconnect = {};
        let oldConnectionId = lxSocketData.reconnect[channelKey] || null;
        lxSocketData.reconnect[channelKey] = self._id;
        lx.app.storage.set('lxsocket', lxSocketData);

        if (oldConnectionId) {
            __sendReconnectionData(self, oldConnectionId);
            return;
        }
    }

    __sendConnectionData(self);
}

function __runHandlers(self, eventName, handlers, payload = {}) {
    if (!handlers.len) return;
    for (let i in handlers) {
        let handler = handlers[i],
            event = lx.isString(eventName)
                ? new lx.socket.ConnectionEvent(eventName, self, payload)
                : eventName;
        handler(event);
    }
}

function __setSocketHandlerOnClose(self) {
    if (self._socket === null) return;
    self._socket.onclose =(e)=>{
        __runHandlers(self, e, self._onclose);
        self._socket = null;
        self._id = null;

        if (self._isReadyForClose) {
            self._status = lx.socket.WebSocketClient.STATUS_CLOSED;
            self._isReadyForClose = false;
        } else {
            self._status = lx.socket.WebSocketClient.STATUS_DISCONNECTED;
            __afterDisconnect(self);
        }
    };
}

function __setSocketHandlerOnError(self) {
    if (self._socket === null) return;
    self._socket.onError =(e)=>{

        //TODO - если канала нет, надо ключ канала убрать отсюда lx.app.storage.get('lxsocket')

        self._errors.push(e);
        __runHandlers(self, e, self._onError);
        self._socket = null;
        self._id = null;
        self._status = lx.socket.WebSocketClient.STATUS_DISCONNECTED;
        __afterDisconnect(self);
    };
}

function __afterDisconnect(self) {
    if (!self._reconnectionAllowed) return;

    let duration = self._reconnectionStep,
        next = self._reconnectionStep + self._reconnectionNextStep;
    self._reconnectionStep = self._reconnectionNextStep;
    self._reconnectionNextStep = next;

    if (duration) {
        self._status = lx.socket.WebSocketClient.STATUS_WAITING_FOR_RECONNECTING;
        let timer = new lx.Timer(duration * 500);
        timer.onCycleEnds(()=>{
            self._status = lx.socket.WebSocketClient.STATUS_DISCONNECTED;
            self.reconnect();
            timer.stop();
            delete self.timer;
        });
        timer.start();
        self.timer = timer;
    } else self.reconnect();
}

function __prepareMessageForSend(data, receivers, privateMode) {
    let result = {__data__:data, __metaData__:{}};
    if (receivers) result.__metaData__.receivers = receivers;
    result.__metaData__.private = (!result.__metaData__.receivers) ? false : privateMode;
    return result;
}

function __getRequestKey(self) {
    if (self.__qCounter == 999999) self.__qCounter = 0;
    let result = self.__qCounter;
    self.__qCounter++;
    result += '_' + lx.Math.randomInteger(0, 999999) + '_' + Date.now() + '_' + (new Date).getMilliseconds();
    return self.__qCounter;
}

function __processEvent(self, msg) {
    let event = new lx.socket.ChannelEvent(msg.__event__, self, msg);
    self._onEvent.forEach(handler => {
        if (handler instanceof lx.socket.EventListener)
            handler.processEvent(event);
        else if (lx.isFunction(handler))
            handler(event);
    });
}

function __sendConnectionData(self) {
    let data = {
        __lxws_action__: 'connect',
        channelOpenData: self._channelOpenData || true
    };
    if (self._channelAuthData) data.auth = self._channelAuthData;
    __sendData(self, data);
}

function __sendReconnectionData(self, oldId) {
    let data = {
        __lxws_action__: 'reconnect',
        channelOpenData: self._channelOpenData || true,
        oldConnectionId: oldId
    };
    if (self._channelAuthData) data.auth = self._channelAuthData;
    __sendData(self, data);
}

function __sendData(self, data) {
    if (!self.isConnected()) return;
    for (let i in self._onBeforeSend) {
        let handler = self._onBeforeSend[i],
            event = new lx.socket.ConnectionEvent('beforeSend', this, data);
        if (!handler(event)) return;
    }
    self._socket.send(JSON.stringify(data));
}
