#lx:namespace lx.socket;
class EventListener
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

        event = this.preprocessEvent(event);

        if (this.lxHasMethod(methodName)) this[methodName](event);
        else this.onEvent(event);
    }

    preprocessEvent(event) {
        return event;
    }

    onEvent(event) {
        // pass
    }
}
