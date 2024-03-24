import{M as e,u as l,r as a,b as t,c as u,d as o,k as d,h as s,X as m,j as r,V as n,g as p,a9 as c,m as i,e as v,a1 as h,L as V}from"./index.1706205343582.js";const _={class:"manager-setting bg-white p-4 rounded-lg"},f=v("h2",{class:"mb-4"},"站点信息配置",-1),g=["src"],b=v("h2",{class:"mb-4"},"邮件服务器配置（如使用25端口,需要检查服务器商是否允许25端口）",-1),w=v("h2",{class:"mb-4"},"其他配置",-1),y={class:"mb-4 flex items-center"},U=v("img",{width:"22",height:"22",class:"mr-2",src:"/dist/assets/auth.1706205343582.svg",alt:"订阅授权码"},null,-1),x={key:0,href:"https://mtab.cc/pricing.html",target:"_blank",class:"text-xs p-1 px-4 bg-gradient-to-r from-cyan-500 to-blue-500 rounded-xl ml-8 text-white"},k={class:"flex"},C={__name:"setting",setup(C){e.get("user_id"),e.get("token","");const S=l(),H=a({logo:"",title:"",email:"",backgroundImage:"",smtp_host:"",smtp_email:"",smtp_password:"",smtp_port:"",assets_host:"",authCode:"",remote_avatar:"",defaultTab:"",recordNumber:"",description:"",authServer:"",customHead:""}),I=e=>{1===e.code&&(H.value.logo=e.data.url)},N=async()=>{const e=await m({url:"/setting/refreshCache"});V.success(e.msg)},O=()=>{m({url:"/setting/saveSetting",method:"post",data:{form:H.value}}).then((e=>{V.success(e.msg),1===e.code&&P()}))},P=()=>{m({url:"/setting/getSetting",method:"get"}).then((e=>{1===e.code&&(H.value=e.data)}))};return P(),(e,l)=>{const a=t("el-icon"),m=t("el-upload"),V=t("el-form-item"),C=t("el-input"),P=t("el-card"),T=t("el-button"),j=t("el-form");return u(),o("div",_,[d(j,{modelValue:H.value,"onUpdate:modelValue":l[14]||(l[14]=e=>H.value=e),"label-position":"top"},{default:s((()=>[d(P,null,{default:s((()=>[f,d(V,{label:"站点logo (建议尺寸128x128px)"},{default:s((()=>[d(m,{class:"avatar-uploader","show-file-list":!1,"on-success":I,action:r(n)+r(S).state.site.upload},{default:s((()=>{var e,l;return[(null==(e=H.value)?void 0:e.logo)?(u(),o("img",{key:0,src:null==(l=H.value)?void 0:l.logo,class:"avatar",alt:""},null,8,g)):(u(),p(a,{key:1,class:"avatar-uploader-icon"},{default:s((()=>[d(r(c))])),_:1}))]})),_:1},8,["action"])])),_:1}),d(V,{label:"站点名称"},{default:s((()=>[d(C,{modelValue:H.value.title,"onUpdate:modelValue":l[0]||(l[0]=e=>H.value.title=e),placeholder:"站点名称"},null,8,["modelValue"])])),_:1}),d(V,{label:"站点关键字"},{default:s((()=>[d(C,{modelValue:H.value.keywords,"onUpdate:modelValue":l[1]||(l[1]=e=>H.value.keywords=e),placeholder:"站点关键字用,隔开"},null,8,["modelValue"])])),_:1}),d(V,{label:"站点介绍"},{default:s((()=>[d(C,{modelValue:H.value.description,"onUpdate:modelValue":l[2]||(l[2]=e=>H.value.description=e),placeholder:"站点介绍"},null,8,["modelValue"])])),_:1}),d(V,{label:"站点联系邮箱"},{default:s((()=>[d(C,{modelValue:H.value.email,"onUpdate:modelValue":l[3]||(l[3]=e=>H.value.email=e),placeholder:"联系客服邮箱"},null,8,["modelValue"])])),_:1}),d(V,{label:"自定义Head代码"},{default:s((()=>[d(C,{type:"textarea",rows:"5",modelValue:H.value.customHead,"onUpdate:modelValue":l[4]||(l[4]=e=>H.value.customHead=e),placeholder:"请粘贴html文本内容"},null,8,["modelValue"])])),_:1}),d(V,{label:"站点备案信息"},{default:s((()=>[d(C,{modelValue:H.value.recordNumber,"onUpdate:modelValue":l[5]||(l[5]=e=>H.value.recordNumber=e),placeholder:"请输入备案号"},null,8,["modelValue"])])),_:1})])),_:1}),d(P,{class:"mt-4"},{default:s((()=>[b,d(V,{label:"SMTP HOST"},{default:s((()=>[d(C,{type:"text",modelValue:H.value.smtp_host,"onUpdate:modelValue":l[6]||(l[6]=e=>H.value.smtp_host=e),placeholder:"邮件服务器地址"},null,8,["modelValue"])])),_:1}),d(V,{label:"邮箱账号"},{default:s((()=>[d(C,{modelValue:H.value.smtp_email,"onUpdate:modelValue":l[7]||(l[7]=e=>H.value.smtp_email=e),placeholder:"发件人邮箱"},null,8,["modelValue"])])),_:1}),d(V,{label:"邮箱授权码/密码"},{default:s((()=>[d(C,{type:"password","show-password":"",modelValue:H.value.smtp_password,"onUpdate:modelValue":l[8]||(l[8]=e=>H.value.smtp_password=e),placeholder:"授权码/密码"},null,8,["modelValue"])])),_:1}),d(V,{label:"发件端口"},{default:s((()=>[d(C,{modelValue:H.value.smtp_port,"onUpdate:modelValue":l[9]||(l[9]=e=>H.value.smtp_port=e),placeholder:"25/109/110/143/465/995/993/994"},null,8,["modelValue"])])),_:1})])),_:1}),d(P,{class:"mt-4"},{default:s((()=>[w,r(false)?(u(),p(V,{key:0,label:"资源cdn域名"},{default:s((()=>[d(C,{modelValue:H.value.assets_host,"onUpdate:modelValue":l[10]||(l[10]=e=>H.value.assets_host=e),placeholder:"文件cdn域名,一般留空即可!"},null,8,["modelValue"])])),_:1})):i("",!0),d(V,{label:"标签LOGO生成API（自建请修改API地址）"},{default:s((()=>[d(C,{modelValue:H.value.remote_avatar,"onUpdate:modelValue":l[11]||(l[11]=e=>H.value.remote_avatar=e),placeholder:"https://avatar.mtab.cc/6.x/thumbs/png?seed="},null,8,["modelValue"])])),_:1})])),_:1}),d(P,{class:"mt-4"},{default:s((()=>[v("h2",y,[U,h(" 高级订阅配置 "),H.value.authCode?i("",!0):(u(),o("a",x,"获取订阅授权"))]),d(V,{label:"授权码配置"},{default:s((()=>[d(C,{modelValue:H.value.authCode,"onUpdate:modelValue":l[12]||(l[12]=e=>H.value.authCode=e),placeholder:"请输入授权码"},null,8,["modelValue"])])),_:1}),r(false)?(u(),p(V,{key:0,label:"授权服务器"},{default:s((()=>[d(C,{modelValue:H.value.authServer,"onUpdate:modelValue":l[13]||(l[13]=e=>H.value.authServer=e),placeholder:"一般留空即可!默认无需填写"},null,8,["modelValue"])])),_:1})):i("",!0)])),_:1}),v("div",k,[d(T,{size:"large",class:"mt-4 w-full",type:"primary",onClick:O},{default:s((()=>[h("保存配置文件")])),_:1}),d(T,{size:"large",class:"mt-4 w-40",type:"danger",onClick:N},{default:s((()=>[h("刷新配置文件缓存")])),_:1})])])),_:1},8,["modelValue"])])}}};export{C as default};