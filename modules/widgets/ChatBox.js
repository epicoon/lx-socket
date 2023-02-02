#lx:module lx.socket.ChatBox;
#lx:module-data {
    i18n: ChatBoxI18n.yaml
};

#lx:use lx.Box;
#lx:use lx.MultiBox;
#lx:use lx.JointMover;
#lx:use lx.Dropbox;
#lx:use lx.Textarea;
#lx:use lx.Button;
#lx:use lx.Scroll;

/**
 * @widget lx.MultiBox
 * @content-disallowed
 */
#lx:namespace lx.socket;
class ChatBox extends lx.Box {
    getBasicCss() {
        return {
            main: 'lxSocket-ChatBox',
            gear: 'lxSocket-ChatBox-gear',
            localMessage: 'lxSocket-ChatBox-localMsg',
            outerMessage: 'lxSocket-ChatBox-outerMsg',
            messageTitle: 'lxSocket-ChatBox-msgTitle',
            indicator: 'lxSocket-ChatBox-indicator',
            indicatorOff: 'lxSocket-ChatBox-indicator-off',
            indicatorOn: 'lxSocket-ChatBox-indicator-on',
        };
    }

    static initCss(css) {
        css.inheritClass('lxSocket-ChatBox', 'AbstractBox');
        css.addClass('lxSocket-ChatBox-gear', {
            color: css.preset.widgetIconColor,
            '@icon': ['\\2699', {fontSize:14}],
            cursor: 'pointer'
        });

        css.addAbstractClass('chatMsg', {
            paddingTop: '5px',
            paddingBottom: '5px',
            width: 'fit-content',
            maxWidth: '80%',
            height: '100%',
            borderRadius: css.preset.borderRadius,
            backgroundColor: css.preset.widgetBackgroundColor,
            color: css.preset.widgetIconColor
        });
        css.inheritClass('lxSocket-ChatBox-localMsg', 'chatMsg', {
            float: 'right',
            marginRight: '5%'
        });
        css.inheritClass('lxSocket-ChatBox-outerMsg', 'chatMsg', {
            float: 'left',
            marginLeft: '2%'
        });

        css.addClass('lxSocket-ChatBox-msgTitle', {
            fontSize: '0.7em',
            fontWeight: 'bold'
        });

        css.addClass('lxSocket-ChatBox-indicator', {
            '@icon': ['\\25C9', {fontSize:14}],
            cursor: 'pointer'
        });
        css.addClass('lxSocket-ChatBox-indicator-off', {
            color: css.preset.hotLightColor
        });
        css.addClass('lxSocket-ChatBox-indicator-on', {
            color: css.preset.checkedLightColor
        });
    }

    /**
     * @widget-init
     *
     * @param [config] {Object: {
     *     #merge(lx.Rect::constructor::config),
     *     [chatId = 1] {Number|String},
     *     [mateNameField = 'name'] {String}
     * }}
     */
    build(config) {
        super.build(config);

        this.chatId = config.chatId || 1;
        this.mateNameField = config.mateNameField || 'name';

        this.streamProportional({direction: lx.VERTICAL});

        const header = this.add(lx.Box, {
            key: 'header',
            height: '50px'
        });

        let wrapper = this.add(lx.Box);
        const body = wrapper.add(lx.MultiBox, {
            key: 'chat',
            geom: [0, 0, null, null, 0, '50px'],
            marks: [ 'All' ],
            basicCss: {main: null},
            animation: true,
            joint: true,
            marksStyle: lx.MultiBox.STYLE_STREAM,
            appendAllowed: true,
            dropAllowed: true
        });
        wrapper.add(lx.JointMover, { bottom: '50px' });
        const footer = wrapper.add(lx.Box, { key: 'footer' });

        header.gridProportional({indent: '10px', paddingBottom: 0});
        //TODO settings - change name, change marks location
        header.add(lx.Box, {key:'settings', css: this.basicCss.gear});
        header->settings.align(lx.CENTER, lx.MIDDLE);
        header.add(lx.Dropbox, {
            key: 'mateChoice',
            width: 10
        });
        header.add(lx.Box, {key: 'indicator', css: this.basicCss.indicator});
        header->indicator.align(lx.CENTER, lx.MIDDLE);

        footer.gridProportional({indent: '10px', paddingTop: 0, minHeight: '20px'});
        footer.add(lx.Textarea, {
            key: 'message',
            width: 9
        });
        footer.add(lx.Button, {
            key: 'send',
            text: #lx:i18n(send),
            width: 3
        });

        body.mark(0).removeDelButton();
        __initSheet(this, this->>chat.sheet(0));
    }

    #lx:client clientBuild(config) {
        super.clientBuild(config);

        this.socket = null;
        this.chatList = new lxChatList(this);
        this.indicator = #lx:model {
            status: {default: 'disconnected'}
        };
        const indicator = this->>indicator;
        const self = this;
        indicator.setField('status', function (val) {
            this.removeClass(self.basicCss.indicatorOff);
            this.removeClass(self.basicCss.indicatorOn);
            switch (val) {
                case 'disconnected':
                    this.addClass(self.basicCss.indicatorOff);
                    break;
                case 'connected':
                    this.addClass(self.basicCss.indicatorOn);
                    break;
            }
        });
        indicator.bind(this.indicator);

        this->>message.on('keydown', (e)=>{
            if (e.key == 'Enter') {
                if (lx.app.keyboard.shiftPressed()) return;
                e.preventDefault();
                this.sendMessage();
            }
        });
        this->>mateChoice.on('change', (e)=>__onChooseMate(self, e.newValue));
        this->>chat.on('selected', (e)=>this.chatList.setActive(e.mark));
    }

    #lx:client {
        setSocket(socket) {
            this.socket = socket;
            this.socket.onPromisedConnection(()=>__initConnection(this));
        }

        receiveMessage(message, senderId, isPrivate) {
            this.chatList.addMessage(message, senderId, isPrivate);
        }

        sendMessage() {
            if (!this.socket || !this.socket.isConnected()) {
                lx.tostWarning(#lx:i18n(noConnection));
                return;
            }
            
            const messageBox = this->>message;
            let message = messageBox.value();
            messageBox.value('');
            this.chatList.addMessage(message);

            let isPrivate = !this.chatList.isCommonActive(),
                receivers = isPrivate ? [this.chatList.active] : null;
            this.socket.send({
                lxChatBox: this.chatId,
                from: this.socket.getLocalMate().getId(),
                isPrivate,
                message
            }, receivers);
        }
    }
}


/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * PRIVATE
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

function __initSheet(self, sheet) {
    sheet.addContainer();
    sheet.addStructure(lx.Scroll, {key: 'scroll', type: lx.VERTICAL});
    const stream = sheet.add(lx.Box, {key: 'stream'});
    stream.stream({indent: '10px', height: 'auto'});
}

#lx:client {
    function __prepareMessage(self, message, sender = null) {
        message = message.replace(/(\r|\n|\r\n)/g, '<br>');
        return sender
            ? '<span class="' + self.basicCss.messageTitle + '">' + sender + ':</span><br>' + message
            : message;
    }

    function __addMessage(self, chatBox, message, local) {
        let msgRow = chatBox->>stream.add(lx.Box);
        let msgWrapper = msgRow.add(lx.Box, {
            css: local ? self.basicCss.localMessage : self.basicCss.outerMessage,
        });
        let text = msgWrapper.add(lx.Box, {
            height: '100%',
            text: message
        });
        text.align(lx.LEFT, lx.MIDDLE);
        chatBox.scrollTo({yShift: 1});
        //TODO - scrolling is not sharp
    }

    function __initConnection(self) {
        const socket = self.socket;
        self.indicator.status = 'connected';
        __updateMateChoice(self);

        // Handlers
        socket.onMessage((message)=>{
            const data = message.getData();
            if (!data.lxChatBox || data.lxChatBox != self.chatId) return;
            if (message.getAuthor().isLocal()) {
                //TODO - send confirm
                return;
            }
            self.receiveMessage(data.message, data.from, data.isPrivate);
        });
        socket.onAddOpenData((e)=>{
            if (!(self.mateNameField in e.payload.newData) || e.payload.mate.isLocal()) return;
            //TODO update marks on change mate name
            __updateMateChoice(self);
        });
        socket.onClose((e)=>{ self.indicator.status = 'disconnected'; });
        socket.onError((e)=>{ self.indicator.status = 'disconnected'; });

        // Set the local connection name if not initialized
        const local = socket.getLocalMate();
        if (!(local.hasParam(self.mateNameField))) {
            let payload = {};
            payload[self.mateNameField] = __getDefaultName(self);
            socket.addOpenData(payload);
        }
    }

    function __updateMateChoice(self) {
        let socket = self.socket,
            mates = socket.getChannelMates(),
            names = {};
        for (let id in mates) {
            let mate = mates[id];
            if (mate.isLocal()) continue;
            names[id] = mate[self.mateNameField] || 'noname';
        }
        self->>mateChoice.options(names);
    }

    function __onChooseMate(self, mateId) {
        self->>mateChoice.value(null);
        if (self.chatList.has(mateId))
            self.chatList.focus(mateId);
        else {
            self.chatList.add(mateId);
            self.chatList.focus(mateId);
        }
    }

    function __getDefaultName(self) {
        let delaultNames = __getDefaultNames(),
            names = [],
            mates = self.socket.getChannelMates(),
            newName = null;

        for (let i in mates) {
            let name = mates[i][self.mateNameField];
            if (name) names.push(name);
        }

        for (let i in delaultNames) {
            let delaultName = delaultNames[i];
            if (names.includes(delaultName)) continue;
            newName = delaultName;
            break;
        }

        if (!newName) newName = #lx:i18n(newMate);
        return newName;
    }
    
    function __getDefaultNames() {
        return [
            #lx:i18n(Dog),
            #lx:i18n(Cow),
            #lx:i18n(Cat),
            #lx:i18n(Horse),
            #lx:i18n(Donkey),
            #lx:i18n(Tiger),
            #lx:i18n(Lion),
            #lx:i18n(Panther),
            #lx:i18n(Leopard),
            #lx:i18n(Cheetah),
            #lx:i18n(Bear),
            #lx:i18n(Elephant),
            #lx:i18n(PolarBear),
            #lx:i18n(Turtle),
            #lx:i18n(Tortoise),
            #lx:i18n(Crocodile),
            #lx:i18n(Rabbit),
            #lx:i18n(Porcupine),
            #lx:i18n(Hare),
            #lx:i18n(Hen),
            #lx:i18n(Pigeon),
            #lx:i18n(Albatross),
            #lx:i18n(Crow),
            #lx:i18n(Fish),
            #lx:i18n(Dolphin),
            #lx:i18n(Frog),
            #lx:i18n(Whale),
            #lx:i18n(Alligator),
            #lx:i18n(Eagle),
            #lx:i18n(FlyingSquirrel),
            #lx:i18n(Ostrich),
            #lx:i18n(Fox),
            #lx:i18n(Goat),
            #lx:i18n(Jackal),
            #lx:i18n(Emu),
            #lx:i18n(Armadillo),
            #lx:i18n(Eel),
            #lx:i18n(Goose),
            #lx:i18n(ArcticFox),
            #lx:i18n(Wolf),
            #lx:i18n(Beagle),
            #lx:i18n(Gorilla),
            #lx:i18n(Chimpanzee),
            #lx:i18n(Monkey),
            #lx:i18n(Beaver),
            #lx:i18n(Orangutan),
            #lx:i18n(Antelope),
            #lx:i18n(Bat),
            #lx:i18n(Badger),
            #lx:i18n(Giraffe),
            #lx:i18n(HermitCrab),
            #lx:i18n(GiantPanda),
            #lx:i18n(Hamster),
            #lx:i18n(Cobra),
            #lx:i18n(HammerheadShark),
            #lx:i18n(Camel),
            #lx:i18n(Hawk),
            #lx:i18n(Deer),
            #lx:i18n(Chameleon),
            #lx:i18n(Hippopotamus),
            #lx:i18n(Jaguar),
            #lx:i18n(Chihuahua),
            #lx:i18n(KingCobra),
            #lx:i18n(Ibex),
            #lx:i18n(Lizard),
            #lx:i18n(Koala),
            #lx:i18n(Kangaroo),
            #lx:i18n(Iguana),
            #lx:i18n(Llama),
            #lx:i18n(Chinchillas),
            #lx:i18n(Dodo),
            #lx:i18n(Jellyfish),
            #lx:i18n(Rhinoceros),
            #lx:i18n(Hedgehog),
            #lx:i18n(Zebra),
            #lx:i18n(Possum),
            #lx:i18n(Wombat),
            #lx:i18n(Bison),
            #lx:i18n(Bull),
            #lx:i18n(Buffalo),
            #lx:i18n(Sheep),
            #lx:i18n(Meerkat),
            #lx:i18n(Mouse),
            #lx:i18n(Otter),
            #lx:i18n(Sloth),
            #lx:i18n(Owl),
            #lx:i18n(Vulture),
            #lx:i18n(Flamingo),
            #lx:i18n(Racoon),
            #lx:i18n(Mole),
            #lx:i18n(Duck),
            #lx:i18n(Swan),
            #lx:i18n(Lynx),
            #lx:i18n(MonitorLizard),
            #lx:i18n(Elk),
            #lx:i18n(Boar),
            #lx:i18n(Lemur),
            #lx:i18n(Mule),
            #lx:i18n(Baboon),
            #lx:i18n(Mammoth),
            #lx:i18n(Blue),
            #lx:i18n(Rat),
            #lx:i18n(Snake),
            #lx:i18n(Peacock)
        ];
    }

    class lxChatList {
        constructor(widget) {
            this.widget = widget;
            this.active = '_';
            this.boxes = {
                '_' : new lxChatBox(widget, widget->>chat.mark(0), '_')
            };
        }

        setActive(mark) {
            this.active = mark.__mateId;
        }

        isCommonActive() {
            return this.active == '_';
        }

        add(mateId) {
            if (this.has(mateId)) return;
            const socket = this.widget.socket;
            if (!socket) return;
            const mate = socket.getChannelMate(mateId);
            if (!mate) return;
            let name = mate[this.widget.mateNameField];
            const mark = this.widget->>chat.appendMark(name);
            this.boxes[mateId] = new lxChatBox(this.widget, mark, mateId);
            __initSheet(this.widget, this.getMateChatBox(mateId).getSheet());
        }

        has(mateId) {
            return (mateId in this.boxes);
        }

        focus(mateId) {
            if (!this.has(mateId)) return;
            this.boxes[mateId].focus();
            this.active = mateId;
        }

        getMateChatBox(mateId) {
            if (!this.has(mateId)) return null;
            return this.boxes[mateId];
        }

        getActiveChatBox() {
            return this.boxes[this.active];
        }

        getCommonChatBox() {
            return this.boxes['_'];
        }

        addMessage(message, senderId, isPrivate) {
            const socket = this.widget.socket;
            let chatBox, mate;
            if (senderId) {
                if (isPrivate) {
                    this.add(senderId);
                    chatBox = this.getMateChatBox(senderId);
                } else chatBox = this.getCommonChatBox();
                mate = socket.getChannelMate(senderId);
            } else {
                chatBox = this.getActiveChatBox();
                mate = socket.getLocalMate()
            }

            chatBox.addMessage(message, mate);
        }
    }

    class lxChatBox {
        constructor(widget, mark, mateId) {
            this.widget = widget;
            this.mark = mark;
            this.mark.__mateId = mateId;
            this.lastAuthor = null;
        }

        focus() {
            this.widget->>chat.select(this.mark.index);
        }

        getSheet() {
            return this.widget->>chat.sheet(this.mark.index);
        }

        addMessage(message, mate) {
            //TODO unread msg counter on marks

            let senderName = (this.lastAuthor === mate.getId())
                ? null
                : (mate.isLocal() ? 'You' : mate[this.widget.mateNameField]);
            this.lastAuthor = mate.getId();

            let processedMessage = __prepareMessage(this.widget, message, senderName);
            __addMessage(this.widget, this.getSheet(), processedMessage, mate.isLocal());
        }
    }
}
