export const Tools = {
    input : {
        numeric: function(opts){
            $('input.ui-numeric').each(function(){
                let input = $(this);
                input.removeClass('ui-numeric');
                if(!opts || opts.readonly !== false)
                input.attr('readonly','readonly');

                let id = 'ui' + Tools.uniqid();
                $(this).replaceWith($('<div/>', {
                    id: id,
                    class: 'ui-numeric d-flex align-items-center'
                }));

                let block = $('#' + id);
                block.append(input);

                input.before($('<div/>', {
                    class: 'ui-numeric-spinner d-flex align-items-center justify-content-center',
                    'data-id': id,
                    'data-dir': 'minus',
                    html: '<i class="icon-ic-minus"></i>'
                }));

                input.after($('<div/>', {
                    class: 'ui-numeric-spinner d-flex align-items-center justify-content-center',
                    'data-id': id,
                    'data-dir': 'plus',
                    html: '<i class="icon-ic-plus"></i>'
                }));

                $('.ui-numeric-spinner[data-id="' + id + '"]').click(function(){
                    let input = $(this).parent().find('input');
                    let val = parseInt(input.val());

                    val += $(this).data('dir') == 'plus' ? 1 : -1;
                    if(val < 1) val = 1;

                    input.val(val);
                    input.change();
                });
            });
        },
        password: function($input) {

            $('input[type="password"]').each(function(){
                $(this).after('<i class="icon-ic-pass-show password-toggler"></i>');
            });

        },
        reg: function($input,reg) {
            $input.bind("change keyup input click", function() {
                if (this.value.match(reg)) {
                    this.value = this.value.replace(reg, '');
                }
            });
        },
        range: function($range,$input1,$input2){

            let instance;
            let min = parseInt($input1.data('min'));
            let max = parseInt($input1.data('max'));
            let from = parseInt($input1.val());
            let to = $input2 ? parseInt($input2.val()) : '';

            let range_params = {
                skin: 'round',
                min: min,
                max: max,
                from: from,
                to: to,
                hide_min_max: true,
                hide_from_to: true,
                onStart: function(data) {
                    $input1.prop("value", data.from);

                    if($input2 && $input2.length)
                        $input2.prop("value", data.to);
                },
                onChange: function(data) {
                    $input1.prop("value", data.from);

                    if($input2 && $input2.length)
                        $input2.prop("value", data.to);
                }
            };
            if($input2 && $input2.length)
                range_params.type = 'double';
            $range.ionRangeSlider(range_params);

            instance = $range.data("ionRangeSlider");

            $input1.on("input", function() {
                let val = $(this).prop("value");

                if (val < min) {
                    val = min;
                } else if (val > max) {
                    val = max;
                }

                instance.update({
                    from: val
                });
            });

            instance.update({
                from: $input1.val()
            });

            if($input2 && $input2.length) {
                $input2.on("input", function() {
                    let val = $(this).prop("value");

                    if (val < min) {
                        val = min;
                    } else if (val > max) {
                        val = max;
                    }

                    instance.update({
                        to: val
                    });
                });

                instance.update({
                    to: $input2.val()
                });
            }

            return instance;

        }
    },
    toast: function(type,text){
        $.toast({
            heading: 'Ошибка',
            text: text,
            // hideAfter : false,
            icon: type,
            position: 'bottom-right'
        });
    },
    resetForm: function($form) {
        if($form.length) {
            $form[0].reset();
            $form.find('.is-invalid').removeClass('is-invalid');
        }
    },
    clickout: function($element,callback) {
        $(document).mouseup(function (e){
            var div = $element;
            if (!div.is(e.target)
                && div.has(e.target).length === 0) {
                callback($element);
            }
        });
    },
    capitalize: function(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    },
    modal: function(template,data,opts) {

        let ajaxData = opts || {};
        ajaxData.template = template;
        ajaxData.data = data || {};

        $('.modal').modal('hide');

        let callback = opts ? opts.callback : function () {} || function(){};

        Tools.ajax('get_modal',ajaxData,function(msg){

            $('body').append(msg.html);
            $('#' + msg.id).modal('show');

            $('#' + msg.id).on('hidden.bs.modal', function () {
                $(this).remove();
            });

            if(callback)
                callback(msg);
        });
    },
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
    },
    cookie: {
        set: function(name, value, options = {}) {

            options = {
                path: '/',
            };

            if (options.expires instanceof Date) {
                options.expires = options.expires.toUTCString();
            }

            let updatedCookie = encodeURIComponent(name) + "=" + encodeURIComponent(value);

            for (let optionKey in options) {
                updatedCookie += "; " + optionKey;
                let optionValue = options[optionKey];
                if (optionValue !== true) {
                    updatedCookie += "=" + optionValue;
                }
            }

            document.cookie = updatedCookie;
        },
        get: function(name) {
            let matches = document.cookie.match(new RegExp(
                "(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
            ));
            return matches ? decodeURIComponent(matches[1]) : undefined;
        }
    },
    cut(text,size,end) {
        return text.length > size ? text.slice(0, size) + end : text;
    },
    ajax: function (action,data,success,error,file) {

        let is_obj = typeof action == 'object' ? true : false;

        success = is_obj ? action.success : (success ?? function(){});
        error = is_obj ? action.error : (error ?? function(){});

        data = is_obj ? action.data : data;
        if(!data) data = {};

        data.action = is_obj ? action.action : action;

        let aparams =  {
            type: 'POST',
            dataType: 'json',
            data: data,
            success: success,
            error: error
        };

        if(is_obj && action.url)
            aparams.url = action.url;

        if((is_obj && action.file) || file) {
            aparams.cache = false;
            aparams.processData = false;
            aparams.contentType = false;
        }

        let ajax_object = $.ajax(aparams);

        return ajax_object;
    },
    money: function(n) {
        return parseFloat(n)
            .toFixed(2)
            .replace(/(\d)(?=(\d{3})+\.)/g, "$1 ")
            .replace(" ", ",");
    },
    gdf: function(selector) {

        let data = {};
        const regex = /(?!^|\[)\w+(?=\]|$)/g;

        $(selector).find('input[type="text"],input[type="date"],input[type="email"],input[type="password"],input[type="hidden"],input[type="number"],input[type="checkbox"],input[type="radio"]:checked,select,textarea').each(function(){

            let name = $(this).attr('name');
            if($(this).attr('disabled') == 'disabled') {
                return true;
            }

            let value = $(this).val();
            if($(this).attr('type') == 'checkbox')
                value = $(this).is(':checked') ? 1 : 0;

            if( name && name != '') {

                if(name.match(/\[/gi)) {
                    let attr = name.match(regex);
                    let t = data;
                    for(let j = 0; j< attr.length; j++){

                        if(t[attr[j]] === undefined){
                            t[attr[j]] = {}
                        }

                        if(j === attr.length - 1)
                            t[attr[j]] = value;
                        else
                            t = t[attr[j]]
                    }
                }
                else {
                    if (!data[name])
                        data[name] = value;
                    else {
                        if (!Array.isArray(data[name])) {
                            var val = data[name];
                            data[name] = [];
                            data[name].push(val);
                        }

                        data[name].push(value);
                    }
                }
            }

        });

        return data;
    },
    inArray: function(needle, haystack) {
        var length = haystack.length;
        for(var i = 0; i < length; i++) {
            if(haystack[i] == needle) return true;
        }
        return false;
    },
    priceFormat: function(n) {
        n += "";
        n = new Array(4 - n.length % 3).join("U") + n;
        return n.replace(/([0-9U]{3})/g, "$1 ").replace(/U/g, "");
    },
    urlParam: function(name){
        var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
        if (results==null){
            return null;
        }
        else{
            return results[1] || 0;
        }
    },
    confirm: function (title,text,btnOk,btnCancel,callback) {
        bootbox.confirm({
            title: title,
            message: text,
            buttons: {
                confirm: {
                    label: btnOk
                },
                cancel : {
                    label: btnCancel
                }
            },
            callback: callback
        })
    },
    ldColorByHSL: function(col, amt = 100) {
        let usePound = false;

        if (col[0] == "#") {
            col = col.slice(1);
            usePound = true;
        }

        let result = /^([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})[\da-z]{0,0}$/i.exec(col);

        let r = parseInt(result[1], 16);
        let g = parseInt(result[2], 16);
        let b = parseInt(result[3], 16);
        r /= 255, g /= 255, b /= 255;

        //toHSL
        let max = Math.max(r, g, b),
            min = Math.min(r, g, b);
        let h, s, l = (max + min) / 2;
        if (max == min) {
            h = s = 0;
        } else {
            let d = max - min;
            s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
            switch (max) {
                case r:
                    h = (g - b) / d + (g < b ? 6 : 0);
                    break;
                case g:
                    h = (b - r) / d + 2;
                    break;
                case b:
                    h = (r - g) / d + 4;
                    break;
            }
            h /= 6;
        }

        //add amt to Lightness
        l = l + (1 - l) * amt / 100;

        //back to HEX
        if (s === 0) {
            r = g = b = l;
        } else {
            const hue2rgb = function(p, q, t) {
                if (t < 0) t += 1;
                if (t > 1) t -= 1;
                if (t < 1 / 6) return p + (q - p) * 6 * t;
                if (t < 1 / 2) return q;
                if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
                return p;
            };
            const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
            const p = 2 * l - q;
            r = hue2rgb(p, q, h + 1 / 3);
            g = hue2rgb(p, q, h);
            b = hue2rgb(p, q, h - 1 / 3);
        }
        const toHex = function(x) {
            const hex = Math.round(x * 255).toString(16);
            return hex.length === 1 ? '0' + hex : hex;
        };

        return (usePound?"#":"")+toHex(r)+toHex(g)+toHex(b);
    },
};
