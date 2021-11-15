class ChannelMate #lx:namespace lx.socket
{
    constructor(socket, id, data) {
        this._socket = socket;
        this._id = id;
        this._isLocal = this._id == this._socket.getId();
        this._params = {};

        this._params = data.lxClone();
        for (let key in data) {
            Object.defineProperty(this, key, {
                get: function() {
                    return this._params[key];
                }
            });
        }
    }

    getId() {
        return this._id;
    }

    getSocket() {
        return this._socket;
    }

    isLocal() {
        return this._isLocal;
    }


}
