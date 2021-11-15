class Event extends lx.socket.Message #lx:namespace lx.socket
{
    constructor(eventName, socket, params) {
        super(socket, params);

        this._name = eventName;
    }

    getName() {
        return this._name;
    }
    
}
