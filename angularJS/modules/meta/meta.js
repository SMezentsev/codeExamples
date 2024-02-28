(function(){
    'use strict';

    angular.module('app')
        .directive('ngMeta',function(AppConfig,$timeout,$state) {
            return {
                restrict: 'A',
                scope: {
                    ngModel: '='
                },
                link: function(scope, el, attrs) {

                    var head = $('head');
                    var title = head.find('title').text();
                    if(AppConfig.settings && AppConfig.settings.title) {


                      $timeout(function(){

                          scope.$watch(function(){


                              return $('[ui-view]').find('H2').find('span').text();
                          }, function(newVal, oldVal   ){
                              var page = $('[ui-view]').find('H2').find('span').text();

                            page = page != '' ? ' - ' +  page : '';

                            if($state.current.name == 'login' && page == '') {
                              page = ' - ' +  'Авторизация';
                            }
                            head.find('title').html('');

                            if(head.find('title').val() == '')

                            head.find('title').text( AppConfig.settings.title||title);
                            head.find('title').append( page);

                          })

                      },1000)

                    }

                }
            };
        });

})();
