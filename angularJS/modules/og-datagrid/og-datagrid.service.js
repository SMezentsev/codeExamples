(function(){
    'use strict';

    angular.module('app')

        .service('OgDataGrid',function(Pagination,$q,Server,$timeout,Inform,$location,$rootScope){

            function _ogDataGrid(options) {

                $rootScope.submitProgress = true;
                var getParams = angular.extend({},options.params || {});

                options.object[options.variable] = options.object[options.variable]||{};

                var requested = Pagination.requested();

                if (options.page) {
                    getParams.page = options.page;
                } else {
                    if (requested.page)
                        getParams.page = requested.page;
                }

                if (requested.perPage) {
                    getParams['per-page'] = requested.perPage;
                }

                if (options.object[options.variable].pagination && options.object[options.variable].pagination.meta && options.object[options.variable].pagination.meta.perPage) {
                    getParams['per-page'] = options.object[options.variable].pagination.meta.perPage;
                }

                var updatePage = function() {
                    _getPage().then(function(pageData) {
                        options.object[options.variable] = angular.extend(options.object[options.variable],pageData);
                    });
                };

                updatePage();

//        $rootScope.$watch(function(){
//          return $location.search();
//        },function(){
//          updatePage();
//        });

                function _getPage() {

                    var defer = $q.defer();
                    var normalizedCollection = [];

                    console.log('options',options)

                    options.object[options.variable].pagination = options.object[options.variable].pagination||{};
                    options.object[options.variable].pagination.loading = true;

                    Server(options.server, 'get', options.resource, {cache: false,params: getParams})
                        .then(function(data) {

                            var collection = options.envelope ? data.data[options.envelope] : data.data;
                            if (options.normalize) {
                                angular.forEach(collection, function(instance) {
                                    normalizedCollection.push(options.normalize(instance));
                                });
                            } else {

                                if(options.envelopeKey && typeof collection[options.envelopeKey] != 'undefined') {
                                    normalizedCollection = collection[options.envelopeKey];
                                } else {
                                    normalizedCollection = collection;
                                }
                            }

                            var downloadGetParams,downloadPostParams;
                            //if (options.object[options.variable].downloadParams) {
                            downloadGetParams = angular.copy(data.config.params)||{};

                            og.del(downloadGetParams,'per-page');
                            og.del(downloadGetParams,'page');

                            //downloadPostParams = {columns:[]};
//                angular.forEach(options.object[options.variable].downloadParams,function(item){
//                  downloadPostParams.columns.push({
//                    field: item.field,
//                    title: translate(item.title)
//                  });
//                });
                            //}

                            $timeout(function() {

                                var resolve = {
                                    collection: normalizedCollection,
                                    pagination: Pagination.controls(data.data._meta,function(pageNum) {
                                        _ogDataGrid(angular.extend(options,{page:pageNum}));
                                        //pub.datagrid(object,options.variable,params,pageNum);
                                    },options.urlPagination)

                                };

                                //if (downloadPostParams) {
                                resolve.downloadCreate = function(reportName,params) {

                                    downloadGetParams.download = true;
                                    Inform('datagrid.download-ordered',{
                                        duration: 5000,
                                        type: 'success',
                                        data: {
                                            start_link: '<a href="/report/downloads">',
                                            end_link: '</a>'
                                        }
                                    });

                                    if(params) {
                                        //$.extend(downloadGetParams,$rootScope.$eval(options.params));
                                        $.extend(downloadGetParams,options.params);
                                    }

                                    $.extend(downloadGetParams,options.params);
                                    Server(options.server,'get','reports/'+reportName,{
                                        params: downloadGetParams
                                    }).then(function(){
                                        });

                                };

                                //}

                                $rootScope.submitProgress = false;
                                $rootScope.$broadcast('datagrid');
                                defer.resolve(resolve);
                            });
                        }, function(error){

                            options.object[options.variable].pagination.loading = false;
//                  defer.reject
                            $rootScope.submitProgress = false;

                        });

                    return defer.promise;
                }
            }

            return _ogDataGrid;
        });

})();
