#lx:private;

class EventListener #lx:namespace lx.socket
{
    constructor() {
        this._socket = null;
    }

    setSocket(socket) {
        this._socket = socket;
    }

    processEvent(event) {
        let name = event.getName();
        let methodName = 'on-' + name;
        methodName = methodName.replace(/[-_](.)/g, function(str, match) {
            return match.toUpperCase();
        });

        if (this.lxHasMethod(methodName))
            this[methodName](event);
        else this.onEvent(event);
    }

    onEvent(event) {
        // pass
    }
}
