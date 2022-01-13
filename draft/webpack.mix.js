const mix = require('laravel-mix');
const fs  = require('fs');
const path = require('path');

let getFiles = function (dir) {
    return fs.readdirSync(dir).filter(file => {
        return fs.statSync(`${dir}/${file}`).isFile();
    });
};

function compile_css(dirs) {
    let sass_dir = 'app/assets/sass';
    let styles_dir = 'public/css';

    for(dir of dirs) {
        getFiles(sass_dir + dir ).forEach(function (SASSpath) {
            if(SASSpath.charAt(0) !== '_'){
                mix.sass(sass_dir + dir + SASSpath, styles_dir + dir );
            }
        });
    }
}

function compile_js(dirs) {
    let js_dir = 'app/assets/js';
    let scripts_dir = 'public/js';

    for(dir of dirs) {
        getFiles(js_dir + dir ).forEach(function (JSpath) {
            if(JSpath.charAt(0) !== '_'){
                mix.js(js_dir + dir + JSpath, scripts_dir + dir + JSpath);
            }
        });
    }
}


compile_css([
    '/',
    '/page/'
]);

compile_js([
    '/',
    '/page/'
]);


const Config = {
    uniqid: function (pr, en) {
        var pr = pr || '', en = en || false, result, us;

        this.seed = function (s, w) {
            s = parseInt(s, 10).toString(16);
            return w < s.length ? s.slice(s.length - w) :
                (w > s.length) ? new Array(1 + (w - s.length)).join('0') + s : s;
        };

        result = pr + this.seed(parseInt(new Date().getTime() / 1000, 10), 8)
            + this.seed(Math.floor(Math.random() * 0x75bcd15) + 1, 5);

        if (en) result += (Math.random() * 10).toFixed(8).toString();

        return result;
    }
};

fs.writeFileSync("version.php", '<?php return "' + Config.uniqid() + '";');
