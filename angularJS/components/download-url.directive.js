(function(){
    'use strict';

    angular.module('app')
        .directive('downloadByUrl',function(Dialog,Inform,$compile,$rootScope){
            return {
                restrict: 'A',
                link: function(scope,el,attr) {

                    var options = scope.$eval(attr.downloadByUrl)||{};
//<span style="display:none" loader="{width:'15px'}"></span>
                    var width = "{width:'15px'}";
                    var loader = $('<span style="display:none;margin-left: 10px" class="loader" loader="'+width+'"></span>');

                    loader = $compile(loader)(scope);
                    $(el).append(loader)


                    var prepareCallback  = options.prepareCallback||false;
                    if(prepareCallback||1) {
                        loader = $(el).find('.loader');
                    }

                    scope.downloadUrl = function(url) {
                        if(prepareCallback||1) {

                            $(loader).show();
                        }

                        var alertMessage = false;

                        var xhr = new XMLHttpRequest();
                        xhr.open("GET", url, true); // Notice "HEAD" instead of "GET",
                        //  to get only the header
                        xhr.onreadystatechange = function(data) {

                            if (this.readyState == 4 && this.status == 200) {
                                $.fileDownload(url, {
                                    'prepareCallback': function() {
                                        if(prepareCallback||1) {
                                            loader.hide();
                                        }
                                    },
                                    successCallback: function(){

                                    }
                                })
                                    .done(function () {

                                    })
                                    .fail(function (data, type, error) {

                                    });
//
//                                var a = document.createElement('a');
//                                a.href = url;
//                                a.style.display = 'none';
//                                document.body.appendChild(a);
//                                a.click();

                                //this.DONE) {
                                //callback(parseInt(xhr.getResponseHeader("Content-Length")));
                            } else if( this.status != 200 && this.status) {
                                if(this.status == 400) {

                                    loader.hide();
                                }
                                var response = JSON.parse(this.response);
                                if(!alertMessage) {
                                    /*, заполните все поля корректно*/
                                    Inform(response.message,{
                                        icon: 'thumbs-down',
                                        type: 'danger',
                                        duration: 4000
                                    });
                                    alertMessage = true;

                                }

                            }
                        };

                        xhr.send();

                    };

                    if(options && typeof options.url != 'undefined') {

                        $(el).click(function(){

                            options = scope.$eval(attr.downloadByUrl);
                            Dialog.confirm('Сохранить?',function(){

                                scope.downloadUrl(options.url);

                            });

                        });
                    }

                }
            };
        });

})();