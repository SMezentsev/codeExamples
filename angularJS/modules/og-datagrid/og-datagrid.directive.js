(function(){
    'use strict';

    angular.module('app')

        .directive('innerList',function($interpolate){
            return {
                restrict:'A',
                terminal:true,
                compile: {
                    pre: function(scope,el) {
                        var tpl = el[0];
                        console.log(el);
                    }
                },
                link: function(scope,el,attr) {
                    var tpl = el[0];
                    el.html('');
                    el.html('');
                    var list = scope.$eval(attr.innerList);
                    angular.forEach(list,function(item){
                        var it = $interpolate(tpl)({role:item});
                        console.log(it);
                        el.append($($interpolate(tpl)({role:item})).html());
                    });
                }
            };
        })
        .directive('ogDatagrid',ogDatagridDirective);

    function ogDatagridDirective($timeout,$compile) {

        var dg = {};
        return {
            restrict: 'A',
            terminal: true,
            priority: -1,
            compile: function(el) {

                dg.el = el;
                dg.data = el.attr('og-datagrid');

                return function($scope) {


                    //dg.el.attr('ng-class','{init:!'+dg.data+'.pagination,loading:'+dg.data+'.pagination.loading,\'has-rows\':'+dg.data+'.collection.length>0}');
                    _setdHeader(el,dg,$scope);
                    _setdHeaderFilter(el,dg,$scope);
                    //_setItemTemplates(dg);
                    _setNgRepeat(el,dg);
                    _setMessages(el,dg);
                    _setTools(el,dg);
                    //_renderUxDatagrid(dg);

                    $timeout(function(){

                        el.children().wrapAll('<div class="datagrid-wrap" ng-class="{init:!'+dg.data+'.pagination,loading:'+dg.data+'.pagination.loading,\'has-rows\':'+dg.data+'.collection.length>0}"></div>');

                        if (dg.header) {
                            //$compile(dg.header[0])($scope);
                        }

                        $compile(dg.el.children())($scope);

                        if (dg.tools) {
                            //    $compile(dg.tools[0])($scope);
                            var select = dg.tools.find('.og-datagrid-pagination .nya-bs-select');
                            select.find('.btn').removeClass('btn btn-default');
                        }


                        var unwatch = $scope.$watch(function(){
                            return el.find('.datagrid-data').width();
                        },function(w){
                            el.find('[og-datagrid-tools]').css('min-width',w);
                            el.css('min-width',w);
                        });


                        var unwatch = $scope.$watch(function(){
                            return el.find('.datagrid-search').width();
                        },function(w){
                            el.find('[og-datagrid-search]').css('min-width',w);
                            el.css('min-width',w);
                        });

                        var id = Math.random();

                        var dataEl = el.find('.datagrid-data');
                        var toolsEl = el.find('[og-datagrid-tools]');
                        var setWidth = function(){
                            //el.css('min-width',0);
                            var w = dataEl.width();
                            toolsEl.css('min-width',w);
                            el.css('min-width',w);
                        };

                        //setWidth();

                        var win = $(window).bind('resize.og-datagrid-'+id,function(){
                            setWidth();
                        });

                        $scope.$on('$destroy',function(){
                            unwatch();
                            win.unbind('resize.og-datagrid-'+id);
                        });

                    });
                }
            }
        };
    }

    function _setdHeaderFilter(el,dg,$scope) {

        var header = el.find('[og-datagrid-header-filtetr]',dg.el[0]);

        var columns = header.find('[sort]');
        var sortInput = header.find('[sort-input]');
        var search = header.find('[search]');

        if(search) {
            $.each(search, function(i,column) {

                var add = [];
                column = $(column);
                var field = column.attr('data-field');
                var input = $('<input type="text">').attr('data-field',field).css('margin-top','5px');
                var sort = $(this).find('span');
                var options = $scope.$eval(sort.attr('sort'));
                input.blur(function(){
                    var method = options.method;
                    var filter = [];
                    var value = $(this).val();
                    if(value != '') {
                        filter['filter[' + field + ']'] = value;
                        method($.extend({sort:$(sort).attr('sort-value')},filter), true);
                    }
                });
                column.after(input);
            });
        }



        if(sortInput) {

            $.each(sortInput, function(i,column){
                var c = $(column);
                var options = {};
                options = $scope.$eval(c.attr('sort-input'));

                if(c.attr('sort-input')) {

                    var method = options.method;
                    options = $scope.$eval(c.attr('sort-input'));
                    var input = $('<input style="width:' + (options.width||'100') + 'px" class="form-control" type="text" name="' + options.type + '" />');
                    input.keypress(function(e){
                        if(e.keyCode == 13) {

                            var t = [];
                            t[options.type] =  c.find('input').val();
                            console.log('t',t)
                            method(t);
                        }
                    });

                    c.append(input);
                }

            });

        }

        if(columns) {
            $.each(columns, function(i,column){

                var c = $(column);
                var options = {};

                if(c.attr('sort')) {
                    options = $scope.$eval(c.attr('sort'));
                    c.css('cursor','pointer');
                    c.on('click',function(){
                        header.find('i').remove()
                        var type = c.attr('sort-value')
                        var method = options.method;

                        if((c.attr('sort-value').indexOf('-')) == 0) {
                            c.attr('sort-value', type.replace('-',''));
                        } else {
                            c.attr('sort-value', '-' + type);
                        }

                        if(c.next('i').length) {
                            if(c.next('i').hasClass('fa-caret-up')) {
                                c.next('i').removeClass('fa-caret-up');
                                c.next('i').addClass('fa-caret-down');
                            } else {
                                c.next('i').removeClass('fa-caret-down');
                                c.next('i').addClass('fa-caret-up');
                            }
                        } else {
                            if((c.attr('sort-value').indexOf('-')) == 0) {
                                c.after($('<i style="padding-left:10px" class="fa fa-caret-down"></i>'));
                            } else {
                                c.after($('<i style="padding-left:10px" class="fa fa-caret-up"></i>'));
                            }
                        }

                        method({sort:c.attr('sort-value')});
                    })
                }
            });
        }

        var search = header.find('[search]');
        if(search) {

        }

        if (header.length) {
            dg.header = header;

            dg.header
                .addClass('og-datagrid-header')
                .children()
                .wrapAll('<div class="og-datagrid-header-row datagrid-row"></div>');
            //dg.header.attr('ng-if',dg.data+'.collection.length');
        }

    }


    function _setdHeader(el,dg,$scope) {


        el.find('[og-datagrid-header]').find('span').addClass('center');
        var header = el.find('[og-datagrid-header]',dg.el[0]);

        var columns = header.find('[sort]');
        var sortInput = header.find('[sort-input]');
        var search = header.find('[search]');

        if(search) {
            $.each(search, function(i,column) {

                var add = [];
                column = $(column);
                var field = column.attr('data-field');
                var input = $('<input type="text">').attr('data-field',field).css('margin-top','5px');
                var sort = $(this).find('span');
                var options = $scope.$eval(sort.attr('sort'));
                input.blur(function(){
                    var method = options.method;
                    var filter = [];
                    var value = $(this).val();
                    if(value != '') {
                        filter['filter[' + field + ']'] = value;
                        method($.extend({sort:$(sort).attr('sort-value')},filter));
                    }
                });
                column.after(input);
            });
        }

        if(sortInput) {

            $.each(sortInput, function(i,column){
                var c = $(column);
                var options = {};
                options = $scope.$eval(c.attr('sort-input'));

                if(c.attr('sort-input')) {

                    var method = options.method;
                    options = $scope.$eval(c.attr('sort-input'));
                    var input = $('<input style="width:' + (options.width||'100') + 'px" class="form-control" type="text" name="' + options.type + '" />');
                    input.keypress(function(e){
                        if(e.keyCode == 13) {
                            var t = [];
                            t[options.type] =  c.find('input').val();
                            console.log('t',t);
                            method(t);
                        }
                    });

                    c.prepend(input);

                }

            });

        }

        if(columns) {

            $.each(columns, function(i,column) {

                var c = $(column);
                var options = {};

                if(c.attr('sort')) {

                    options = $scope.$eval(c.attr('sort'));
                    c.css('cursor','pointer');
                    c.on('click',function(){
                        // header.find('i').remove();
                        var type = c.attr('sort-value')
                        var method = options.method;

                        if((c.attr('sort-value').indexOf('-')) == 0) {
                            c.attr('sort-value', type.replace('-',''));
                        } else {
                            c.attr('sort-value', '-' + type);
                        }

                        if(c.next('i').length) {
                            if(c.next('i').hasClass('fa-caret-up')) {
                                c.next('i').removeClass('fa-caret-up');
                                c.next('i').addClass('fa-caret-down');
                            } else {
                                c.next('i').removeClass('fa-caret-down');
                                c.next('i').addClass('fa-caret-up');
                            }
                        } else {
                            if((c.attr('sort-value').indexOf('-')) == 0) {
                                c.after($('<i style="padding-left:10px" class="fa fa-caret-down"></i>'));
                            } else {
                                c.after($('<i style="padding-left:10px" class="fa fa-caret-up"></i>'));
                            }
                        }

                        method({sort:c.attr('sort-value')});
                    })
                }
            });
        }

        var search = header.find('[search]');
        if(search) {

        }

        if (header.length) {
            dg.header = header;
            dg.header
                .addClass('og-datagrid-header')
                .children()
                .wrapAll('<div class="og-datagrid-header-row datagrid-row"></div>');
            //dg.header.attr('ng-if',dg.data+'.collection.length');
        }

    }

    function _setMessages(el,dg) {
        dg.emptyText = el.find('[empty-text]');
        if (dg.emptyText.length) {
            dg.emptyText.remove();
        }
    }

    function _setTools(el,dg){
        var tools = el.find('[og-datagrid-tools]',dg.el[0]);

        if(tools.length) {
            dg.tools = tools;
            ////////////
            var paginations = $('[og-datagrid-pagination]',dg.el[0]);

            var position = 0;

            if (paginations.length) {
                paginations.each(function(i,pagination){
                    position = position + 1;
                    pagination = $(pagination);
                    var perPageLabel = pagination.attr('per-page-label');
                    var perPageList = pagination.attr('per-page-list');
                    pagination
                        .removeAttr('og-datagrid-pagination')
                        .removeAttr('per-page-label')
                        .removeAttr('per-page-list')
                        .attr('ng-cloak','')
                        .addClass('og-datagrid-pagination');
                    //  .attr('ng-if',dg.data+'.collection.length');

                    console.log('positionpositionposition',position)

                    pagination.append($('' +
                        '<span translate="'+(perPageLabel||'datagrid.perPage')+'"></span>' +
                        '<ol class="per-page nya-bs-select inline ' + (position <= 1 ? 'dropdown' : 'dropup') + '" ng-model="'+dg.data+'.pagination.meta.perPage" ng-change="'+dg.data+'.pagination.getPage()" title="{{'+dg.data+'.pagination.meta.perPage}}">' +
                        '<li nya-bs-option="pp in '+(perPageList||'[5,10,20,50]')+'"><a>{{pp}}</a></li>' +
                        '</ol>' +
                        '<span class="info">{{'+dg.data+'.pagination.fromTo()||\'-\'}}/{{'+dg.data+'.pagination.meta.totalCount||\'-\'}}</span>' +
                        '<span class="prev-next">' +
                        '<button class="btn" ng-click="'+dg.data+'.pagination.links.prev.go()" ng-disabled="!'+dg.data+'.pagination.links.prev.go"><fa-icon angle-left></fa-icon></button>' +
                        '<button class="btn" ng-click="'+dg.data+'.pagination.links.next.go()" ng-disabled="!'+dg.data+'.pagination.links.next.go"><fa-icon angle-right></fa-icon></button>' +
                        '</span>' +
                        '<div class="clearfix"></div>'));

                });
            }

            ////////////
            var downloads = $('[og-datagrid-download]',tools[0]);
            if (downloads.length) {

                downloads.each(function(i,download){
                    download = $(download);

                    var downloadDownload = download.attr('og-datagrid-download');
                    var downloadParams = download.attr('og-datagrid-params')||{};

                    if (downloadDownload!=undefined && downloadDownload!='') {
                        ///var format = download.attr('format');
                        download
                            //.removeAttr('og-datagrid-download')
                            .removeAttr('link-label')
                            //.removeAttr('format')
                            .addClass('og-datagrid-download form-inline')
                            .attr('ng-show',dg.data+'.collection.length')
                            //.attr('ng-init',dg.data+'.downloadParams = '+downloadParams)
                        ;
                        //          download.append($(
                        //            '<div class="input-group">' +
                        //              '<input class="form-control" type="text" ng-model="'+dg.data+'.downloadName" placeholder="{{\'datagrid.download-input\'|translate}}"/>' +
                        //              '<span class="input-group-btn">' +
                        //                '<button class="btn btn-default" ng-disabled="!'+dg.data+'.downloadName" ng-click="'+dg.data+'.downloadCreate(\''+(format||'csv')+'\')">' +
                        //                  '<span translate="datagrid.download-button"></span>' +
                        //                '</button>' +
                        //              '</span>' +
                        //            '</div>'
                        //          ));


                        download.append($('<div class="datagrid-download-link">' +
                            '<a href="#" ng-click="'+dg.data+'.downloadCreate(\''+download.attr('og-datagrid-download')+'\',\''+ downloadParams +'\')">' +
                            '<span translate="datagrid.download-button"></span>' +
                            '</a></div>'));

                    }

                });


            }
        }
    }

    function _setNgRepeat(el,dg){

        var template = el.find('[og-datagrid-item]',dg.el[0]);
        if (template.length) {
            dg.ngRepeat = template.attr('ng-cloak','');
//      dg.ngRepeat = $('<div></div>').insertBefore($(template[0]));
//      dg.ngRepeat = $(dg.ngRepeat);

            dg.informers = $('<div class="datagrid-rows-container">' +
                '<div class="datagrid-row datagrid-loading-row"><span loader></span></div>' +
                '</div>');
            dg.informers.append($('<div class="datagrid-row datagrid-empty-row text-warning"><span translate="datagrid.emptyResult"></span></div>')
                //  .append(dg.emptyText?dg.emptyText:$(''))
            );
            dg.ngRepeat.before(dg.informers);

            //var name = template.attr('template')||'default';
            var item = dg.ngRepeat.attr('og-datagrid-item');
            var sort = dg.ngRepeat.attr('og-datagrid-sort'); //| orderBy:'id'
            if(sort) {
                sort = " | orderBy:'" + sort + "'";
            } else {
                sort = '';
            }

            dg.ngRepeat.removeAttr('og-datagrid-item');
//      if (template.attr('no-wrap')==undefined) {
//        template = $('<div></div>').append(template);
//      } else {
            dg.ngRepeat.attr('ng-repeat',item+' in '+dg.data+'.collection '+sort+' track by $index');

            dg.ngRepeat.addClass('datagrid-row');
            //template.wrapAll('<div class="datagrid-row " ng-repeat="'+item+' in '+dg.data+'.collection track by $index"></div>');
            //dg.ngRepeat = template.parent();
//      }


            dg.ngRepeat.find('span').addClass('center');
            dg.ngRepeat.find('span').addClass('word-break');

            //dg.ngRepeat.attr('ng-if',dg.data+'.collection.length');
            dg.ngRepeat.wrap('<div class="datagrid-rows-container datagrid-data"></div>');
            dg.ngRepeat = dg.ngRepeat.parent();


        }
    }

})();
