#lx:module lx.socket.WebSocketClient;

#lx:private;

#lx:require ChannelMate;
#lx:require Message;
#lx:require EventListener;
#lx:require Event;

class WebSocketClient #lx:namespace lx.socket {
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
        this._beforeSend = null;
        this._onConnected = null;
        this._onClientJoin = null;
        this._onClientDisconnected = null;
        this._onClientReconnected = null;
        this._onClientLeave = null;
        this._onEvent = null;
        this._onopen = null;
        this._onmessage = null;
        this._onclose = null;
        this._onError = null;
        this._errors = [];

        this.__qCounter = 0;
        this.__qBuffer = {};

        if (config.handlers) {
            var handlers = config.handlers;
            if (handlers.beforeSend) this._beforeSend = handlers.beforeSend;
            if (handlers.onConnected) this._onConnected = handlers.onConnected;
            if (handlers.onClientJoin) this._onClientJoin = handlers.onClientJoin;
            if (handlers.onClientDisconnected) this._onClientDisconnected = handlers.onClientDisconnected;
            if (handlers.onClientReconnected) this._onClientReconnected = handlers.onClientReconnected;
            if (handlers.onClientLeave) this._onClientLeave = handlers.onClientLeave;
            if (handlers.onChannelEvent) {
                this._onEvent = handlers.onChannelEvent;
                if (this._onEvent instanceof lx.socket.EventListener) this._onEvent.setSocket(this);
            }

            if (handlers.onOpen) this._onopen = handlers.onOpen;
            if (handlers.onMessage) this._onmessage = handlers.onMessage;
            if (handlers.onClose) this._onclose = handlers.onClose;
            if (handlers.onError) this._onError = handlers.onError;

            __setSocketHandlers(this);
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

    connect(channelOpenData = null, password = null) {
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
        this._channelAuthData = password;
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

    close() {
        if (this._reconnectionAllowed && this._channel) {
            var map = lx.Storage.get('lxsocket') || {};
            if (map.reconnect && (this._channel in map.reconnect) && map.reconnect[this._channel] == this._id) {
                delete map.reconnect[this._channel];
                lx.Storage.set('lxsocket', map);
            }
        }

        if (!this.isConnected()) return;
        this.sendData({__lxws_action__:'close'});
    }

    break() {
        if (!this.isConnected()) return;
        this.sendData({__lxws_action__:'break'});
    }

    send(data, receivers = null, privateMode = false) {
        var msg = __prepareMessageForSend(data, receivers, privateMode);
        this.sendData(msg);
    }

    trigger(eventName, data = {}, receivers = null, privateMode = false) {
        var msg = __prepareMessageForSend(data, receivers, privateMode);
        msg.__metaData__.__event__ = eventName;
        this.sendData(msg);
    }

    ask(questionName, data, callback) {
        var msg = __prepareMessageForSend(data, [this._id], true);
        var number = __getQuestionNumber(this);
        this.__qBuffer[number] = callback;
        msg.__metaData__.__question__ = {name:questionName, number};
        this.sendData(msg);
    }

    sendData(data) {
        if (!this.isConnected()) return;
        if (this._beforeSend && !this._beforeSend(data)) return;
        this._socket.send(JSON.stringify(data));
    }

    beforeSend(func) {
        this._beforeSend = func;
    }

    onOpen(func) {
        this._onopen = func;
        __setSocketHandlerOnOpen(this);
    }

    onMessage(func) {
        this._onmessage = func;
        __setSocketHandlerOnMessage(this);
    }

    onClose(func) {
        this._onclose = func;
        __setSocketHandlerOnClose(this);
    }

    onError(func) {
        this._onError = func;
        __setSocketHandlerOnError(this);
    }

    static dropReconnectionsData(list) {
        var lxSocketData = lx.Storage.get('lxsocket') || {};
        for (let i in list) {
            let name = list[i];
            if (name in lxSocketData.reconnect)
                delete lxSocketData.reconnect[name];
        }
        lx.Storage.set('lxsocket', lxSocketData);
    }

    static filterReconnectionsData(list) {
        var lxSocketData = lx.Storage.get('lxsocket') || {},
            newReconnect = {};
        for (let i in list) {
            let name = list[i];
            if (name in lxSocketData.reconnect)
                newReconnect[name] = lxSocketData.reconnect[name];
        }
        lxSocketData.reconnect = newReconnect;
        lx.Storage.set('lxsocket', lxSocketData);
    }
}


/***********************************************************************************************************************
 * PRIVATE
 **********************************************************************************************************************/

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
        if (self._onopen) self._onopen();
    }
}

function __setSocketHandlerOnMessage(self) {
    if (self._socket === null) return;
    self._socket.onmessage =(e)=>{
        let msg = JSON.parse(e.data);

        if (self._id === null) {
            self._id = msg.id;
            self._channelOpenData.id = self._id;
            self._channelMates = {};
            for (var mateId in msg.connections) {
                self._channelMates[mateId] = new lx.socket.ChannelMate(self, mateId, msg.connections[mateId]);
            }
            self._channelData = msg.channelData.isArray ? {} : msg.channelData;
            if (self._onConnected) self._onConnected(self.getChannelMates(), self.getChannelData());

            if (msg.reconnectionAllowed) {
                self._reconnectionAllowed = true;
                self._reconnectionStep = 0;
                self._reconnectionNextStep = 1;

                var channelKey = self._channel,
                    lxSocketData = lx.Storage.get('lxsocket') || {};
                if (!lxSocketData.reconnect) lxSocketData.reconnect = {};
                var oldConnectionId = lxSocketData.reconnect[channelKey] || null;
                lxSocketData.reconnect[channelKey] = self._id;
                lx.Storage.set('lxsocket', lxSocketData);
                if (oldConnectionId) {
                    __sendReconnectionData(self, oldConnectionId);
                    return;
                }
            }

            __sendConnectionData(self);
            return;
        }

        if (msg.__lxws_event__) {
            switch (msg.__lxws_event__) {
                case 'clientJoin':
                    var mate = new lx.socket.ChannelMate(self, msg.client.id, msg.client);
                    self._channelMates[msg.client.id] = mate;
                    if (self._onClientJoin) self._onClientJoin(mate);
                    break;
                case 'clientReconnected':
                    var mate = new lx.socket.ChannelMate(self, msg.client.id, msg.client);
                    self._channelMates[msg.client.id] = mate;
                    if (self._onClientReconnected) self._onClientReconnected(mate, msg.oldConnectionId);
                    break;
                case 'clientDisconnected':
                    var mate = self._channelMates[msg.client.id];
                    delete self._channelMates[msg.client.id];
                    if (self._onClientDisconnected) self._onClientDisconnected(mate);
                    break;
                case 'clientLeave':
                    var mate = self._channelMates[msg.client.id];
                    delete self._channelMates[msg.client.id];
                    if (self._onClientLeave) self._onClientLeave(mate);
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

        if (msg.__event__ && self._onEvent) {
            __processEvent(self, msg);
            return;
        }

        if (msg.__multipleEvents__ && self._onEvent) {
            msg.__multipleEvents__.each(e=>__processEvent(self, e));
            return;
        }

        if (msg.__answer__) {
            if (msg.__answer__ in self.__qBuffer) {
                let f = self.__qBuffer[msg.__answer__];
                delete self.__qBuffer[msg.__answer__];
                f(msg.data);
            }
            return;
        }

        if (msg.__dump__) {
            lx.Alert(msg.__dump__)
            return;
        }

        if (self._onmessage) self._onmessage(new lx.socket.Message(self, msg));
    };
}

function __setSocketHandlerOnClose(self) {
    if (self._socket === null) return;
    self._socket.onclose =(e)=>{
        if (self._onclose) self._onclose(e);
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
        self._errors.push(e);
        if (self._onError) self._onError(e);
        self._socket = null;
        self._id = null;
        self._status = lx.socket.WebSocketClient.STATUS_DISCONNECTED;
        __afterDisconnect(self);
    };
}

function __afterDisconnect(self) {
    if (!self._reconnectionAllowed) return;

    var duration = self._reconnectionStep,
        next = self._reconnectionStep + self._reconnectionNextStep;
    self._reconnectionStep = self._reconnectionNextStep;
    self._reconnectionNextStep = next;

    if (duration) {
        self._status = lx.socket.WebSocketClient.STATUS_WAITING_FOR_RECONNECTING;
        var timer = new lx.Timer(duration * 500);
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
    var result = {__data__:data, __metaData__:{}};
    if (receivers) result.__metaData__.receivers = receivers;
    result.__metaData__.private = (!result.__metaData__.receivers) ? false : privateMode;
    return result;
}

function __getQuestionNumber(self) {
    if (self.__qCounter == 999999) self.__qCounter = 0;
    self.__qCounter++;
    return self.__qCounter;
}

function __processEvent(self, msg) {
    let event = new lx.socket.Event(msg.__event__, self, msg);
    if (self._onEvent instanceof lx.socket.EventListener)
        self._onEvent.processEvent(event);
    else if (self._onEvent.isFunction)
        self._onEvent(event);
}

function __sendConnectionData(self) {
    let data = {
        __lxws_action__: 'connect',
        channelOpenData: self._channelOpenData || true
    };
    if (self._channelAuthData) data.auth = self._channelAuthData;
    self.sendData(data);
}

function __sendReconnectionData(self, oldId) {
    let data = {
        __lxws_action__: 'reconnect',
        channelOpenData: self._channelOpenData || true,
        oldConnectionId: oldId
    };
    if (self._channelAuthData) data.auth = self._channelAuthData;
    self.sendData(data);
}
