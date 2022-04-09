#lx:namespace lx.socket;
class Event extends lx.socket.Message
{
    constructor(eventName, socket, params) {
        super(socket, params);

        this._name = eventName;
    }

    getName() {
        return this._name;
    }
    
}
