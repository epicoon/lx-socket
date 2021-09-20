#lx:private;

class Message #lx:namespace lx.socket
{
    constructor(socket, params) {
        this._socket = socket;
        this._data = params.data;

        this._fromId = params.from;
        this._isToMe = params.toMe;
        this._receivers = params.receivers || [];
        this._isPrivate = params.private;
    }

    getData() {
        return this._data;
    }

    getAuthor() {
        return this._socket.getChannelMate(this._fromId);
    }

    getReceivers() {
        var result = [];
        this._receivers.forEach(id=>result.push(this._socket.getChannelMate(id)));
        return result;
    }

    isPrivate() {
        return this._isPrivate;
    }

    isAddressed() {
        return this._isToMe || this._receivers.len;
    }

    isFromMe() {
        return this.getAuthor().isLocal();
    }

    isToMe() {
        return this._isToMe;
    }
}
