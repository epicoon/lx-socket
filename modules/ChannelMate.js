#lx:namespace lx.socket;
class ChannelMate
{
    constructor(socket, id, data) {
        this._socket = socket;
        this._id = id;
        this._isLocal = this._id == this._socket.getId();
        this._params = {};

        this._params = data.lxClone();
        if (this._params._id)
            delete this._params._id;
        if (this._params._isLocal)
            delete this._params._isLocal;
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

    addParam(key, value) {
        if (key == '_id' || key == '_isLocal')
            return;
        if (!(key in this._params)) {
            Object.defineProperty(this, key, {
                get: function() {
                    return this._params[key];
                }
            });
        }
        this._params[key] = value;
    }

    hasParam(name) {
        return (name in this._params);
    }
}
