/**
 opt:{
 name: "窗口名称",
 src: "logo,
 url: "打开的窗口地址"
 }*
 */
function openCard(opt) {
    if (window.parent && window.parent.openCard) {
        window.parent.openCard(opt)
    }
}

//向书签发送事件消息
function emitter_emit(event, data) {
    if (window.parent && window.parent.emitterBus) {
        window.parent.emitterBus.emit(event, data);
    }
}
//监听书签发送的事件消息
function emitter_on(event, callback) {
    if (window.parent && window.parent.emitterBus) {
        window.parent.emitterBus.on(event, callback);
    }
}
//取消监听书签发送的事件消息
function emitter_off(event, callback) {
    if (window.parent && window.parent.emitterBus) {
        window.parent.emitterBus.off(event, callback);
    }
}