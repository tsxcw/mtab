/**
 opt:{
 name: "窗口名称",
 src: "logo,
 url: "打开的窗口地址"
 }*
 */
let subjectArr = []


function openCard(opt) {
    sendMessage('openCard', opt)
}

//向书签发送事件消息
function emitter_emit(event, data) {
    sendMessage(event, data)
}

//监听书签发送的事件消息
function emitter_on(event, callback) {
    subjectArr.push({
        subject: event,
        callback: callback,
        subjectType: 'on'
    })
}

//取消监听书签发送的事件消息
function emitter_off(event, callback) {
    subjectArr.forEach((item, index) => {
        if (item === event && callback === item.callback) {
            subjectArr.splice(index, 1)
        }
    })
}

function emitter_once(event, callback) {
    subjectArr.push({
        subject: event,
        callback: callback,
        subjectType: 'once'
    })
}

const sendMessage = (subject, message) => {
    let dt = {
        type: 'emitter',
        message: message,
        subject: subject,
    }
    window.parent.postMessage(JSON.stringify(dt), '*')
}



window.addEventListener("message", (event) => {
    try {
        const data = JSON.parse(event.data);
        const {type = null, subject = null, message = ''} = data;
        if (type === 'emitter') {
            subjectArr.forEach(item => {
                if (item.subject === subject) {
                    if (item.callback) {
                        item.callback(message)
                    }
                    if (item.subjectType === 'once') {
                        emitter_once(item.subject, item.callback)
                    }
                }
            })
        }
    } catch (e) {
    }
})


window.addEventListener("load", () => {
    document.body.oncontextmenu = function (event) {
        const {x, y} = event
        emitter_emit("cardMouseRight", {
            left: x,
            top: y,
            data: {
                id: window.name
            }
        });
        return false;
    }


    document.body.addEventListener('mousedown', () => {
        emitter_emit('deskTopMouseClose')
        emitter_emit('mouseMenuClose')
    })
})