window.$ = window.jQuery = require('jquery');

require('bootstrap/dist/js/bootstrap.bundle');

let bootbox = require('bootbox');
window.bootbox = bootbox;

import {Tools} from './tools';
window.Tools = Tools;

require('./ui');

class Base {
    constructor() {

        let protoMain = Object.getPrototypeOf(this);
        let protoBase = Object.getPrototypeOf(protoMain);
        this.callInitByProto(protoBase);
        this.callInitByProto(protoMain);

        $('body').removeClass('preload');

    }

    callInitByProto(proto) {
        let vars = Object.getOwnPropertyNames(proto);

        for(let method of vars) {
            if(method.match(/^init[\w]+/,method)) {
                this[method]();
            }
        }
    }

}

window.Base = Base;
