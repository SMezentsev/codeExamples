(function(){
  'use strict';

  angular.module('app')
    .service('Pagination',PaginationService)

  function PaginationService($location,$rootScope) {
    return {
      requested: function(){
        var requested = {};
        var search = $location.search();
        if (search.page)
          requested.page = search.page;

        if (search['per-page'])
          requested.perPage = search['per-page'];

        return requested;
      },
      ready: function(object,variable,callback){
        var unwatch = $rootScope.$watch(function(){
          return object[variable] && object[variable].pagination;
        },function(val){
          if (val) {
            unwatch();
            callback();
          }
        });
      },
      controls: function(meta,getPageFn,urlPagination){
        var controls = {
          links: {},
          getPage: function(page){
            getPageFn(page);
          },
          fromTo: function(){
            var from = '';
            var to = '';
            if (meta && typeof meta.totalCount != 'undefined' && meta.totalCount==0) {
              return false;
            }
            if (meta && typeof meta.perPage != 'undefined') {
                from = meta.perPage*(meta.currentPage-1)+1;
                to = meta.perPage*meta.currentPage;

                if (to>meta.totalCount) {
                    to = meta.totalCount;
                }
            } else {
                return '';
            }

            return from + '-' + to;
          },
          meta: meta||{}
        };

//        if (urlPagination && meta && meta.currentPage) {
//          $location.search('page',meta.currentPage).replace();
//        }
//
//        if (urlPagination && meta && meta.perPage) {
//          $location.search('per-page',meta.perPage);
//        }

        if (meta && meta.pageCount>1) {
          controls.links.prev = {
            disabled: true
          };
          if (meta.currentPage>1) {
            controls.links.prev = {
              go: function(){
                getPageFn(meta.currentPage-1);
              }
            };
          }
          controls.links.next = {
            disabled: true
          };
          if (meta.currentPage<meta.pageCount) {
            controls.links.next = {
              go: function(){
                getPageFn(meta.currentPage+1);
              }
            };
          }
//          for (var pageNum=1;pageNum<=meta.pageCount;++pageNum) {
//            controls.links.push({
//              title: pageNum,
//              page: pageNum
//            });
//          }
        }

        return controls;
      }
    }
  }

})();