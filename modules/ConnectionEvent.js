#lx:namespace lx.socket;
class ConnectionEvent {
    constructor(eventName, socket, payload = {}) {
        this._name = eventName;
        this._socket = socket;
        this.payload = payload;
    }

    getName() {
        return this._name;
    }
}
