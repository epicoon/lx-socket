#lx:module lx.socket.WebSocketClient;

#lx:private;

class WebSocketClient #lx:namespace lx.socket {
    constructor(port, channel, handlers) {
        this._port = port || null;
        this._channel = channel || null;
        this._urlPrefix = 'ws://' + document.location.hostname;
        this._channelOpenData = null;
        this._channelPassword = null;

        this._socket = null;
        this._id = null;
        this._channelMates = {};
        this._beforeSend = null;
        this._onClientJoin = null;
        this._onClientLeave = null;
        this._onConnected = null;
        this._onopen = null;
        this._onmessage = null;
        this._onclose = null;
        this._onerror = null;
        this._errors = [];

        if (handlers) {
            if (handlers.beforeSend) this._beforeSend = handlers.beforeSend;
            if (handlers.onClientJoin) this._onClientJoin = handlers.onClientJoin;
            if (handlers.onClientLeave) this._onClientLeave = handlers.onClientLeave;
            if (handlers.onConnected) this._onConnected = handlers.onConnected;

            if (handlers.onOpen) this._onopen = handlers.onOpen;
            if (handlers.onMessage) this._onmessage = handlers.onMessage;
            if (handlers.onClose) this._onclose = handlers.onClose;
            if (handlers.onError) this._onerror = handlers.onError;
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
        if (this._port === null || this._channel === null) return false;
        return this._urlPrefix + ':' + this._port + '/' + this._channel;
    }

    getId() {
        return this._id;
    }

    getChannelOpenData() {
        return this._channelOpenData.lxClone();
    }

    getChannelMates() {
        return this._channelMates.lxClone();
    }

    isConnected() {
        return this._socket !== null;
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
        this._channelPassword = password;
        if (window.MozWebSocket) this._socket = new MozWebSocket(url);
        else if (window.WebSocket) this._socket = new WebSocket(url);
        this._socket.binaryType = 'blob';

        __setSocketHandlers(this);
    }

    disconnect() {
        if (!this.isConnected()) return;

        this._socket.close();
        // this._socket = null;
    }

    send(data) {
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
        this._onerror = func;
        __setSocketHandlerOnError(this);
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
    if (self._onopen === null || self._socket === null) return;
    self._socket.onopen = self._onopen;
}

function __setSocketHandlerOnMessage(self) {
    if (self._onmessage === null || self._socket === null) return;
    self._socket.onmessage =(e)=>{
        let msg = JSON.parse(e.data);

        if (self._id === null) {
            self._id = msg.id;
            self._channelOpenData.id = self._id;
            self._channelMates = msg.connections.isArray ? {} : msg.connections;
            if (self._onConnected) self._onConnected(self.getChannelMates());
            let data = {
                __action__: 'connection',
                channelOpenData: self._channelOpenData || true
            };
            if (self._channelPassword) data.password = self._channelPassword;
            self.send(data);
            return;
        }

        if (msg.__event__) {
            switch (msg.__event__) {
                case 'clientJoin':
                    self._channelMates[msg.client.id] = msg.client;
                    self._onClientJoin(msg.client);
                    break;
                case 'clientLeave':
                    delete self._channelMates[msg.client.id];
                    self._onClientLeave(msg.client);
                    break;
            }
            return;
        }

        self._onmessage(msg);
    };
}

function __setSocketHandlerOnClose(self) {
    if (self._onclose === null || self._socket === null) return;
    self._socket.onclose =(e)=>{
        self._onclose(e);
        self._socket = null;
    };
}

function __setSocketHandlerOnError(self) {
    if (self._onerror === null || self._socket === null) return;
    self._socket.onerror =(e)=>{
        self._errors.push(e);
        self._onerror(e);
        self._socket = null;
    };
}
