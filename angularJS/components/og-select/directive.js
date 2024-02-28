(function(){
  'use strict';

  angular.module('app')
    .config(function(nyaBsConfigProvider){
      nyaBsConfigProvider.setLocalizedText('loc',{
        defaultNoneSelection: ' ',

        // noSearchResultTpl: '<span>Не найдено</span><br>',
        noSearchResultTpl: '',
        numberItemSelected: '%d'
      });
      nyaBsConfigProvider.useLocale('loc');
    })
    .directive('ogSelect',function($compile,$timeout,nyaBsConfig){
      return {
        restrict: 'E',
        terminal: true,
        compile: function(el,attr){

          var checkMark = el.attr('check-mark-icon')||'check';

          var select,solid;
          return {
            pre: function(scope,el,attr){
              var selectedCountText = el.attr('selected-count-text')||'{n}';
              el.removeAttr('selected-count-text');
              nyaBsConfig.numberItemSelected = translate(selectedCountText).replace('{n}','%d');

              var option = el.find('option');

              var liveSearch = '';
              if (attr.liveSearch!=undefined) {
                liveSearch = ' data-live-search="true" ';
              }

              var size = attr.size!=undefined ? attr.size : 12
                  ;
              size = ' size="'+size+'" ';
                var sort = '';

                if(option.attr('sort')) {
                    sort = scope.$eval(option.attr('sort'));
                    sort = " | orderBy: '" + sort.order + "'";
                }

                var allowClear = '';

                if(option.attr('allowClear')) {
                    allowClear = 'allow-clear="true"';
                }

                var group = option.attr('group')!=undefined ? ' group by '+option.attr('item')+'.type' : '';
                var groupHeader = option.attr('group') !=undefined ? '<span style="width:100%; font-size:14px;color:#000;font-weight:bold;" class="dropdown-header">{{$group}}</span>' : '';

              select = $('<div ' + allowClear + ' class="nya-bs-select og-dropdown" '+liveSearch+size+'></div>')
                      .append($('<li ng-if="'+option.attr('list')+'==undefined" class="dropdown-header"><i loader></i></li>'))
                      .append($('<li ng-if="'+option.attr('list')+'!=undefined && !'+option.attr('list')+'.length" class="dropdown-header"><span translate="'+(el.attr('empty-text')||'Empty list')+'"></span></li>'))
                      .append($('<li nya-bs-option="'+option.attr('item')+' in '+option.attr('list')+' '+(option.attr('list-params')||'') + ' ' + sort + ' ' + group + '" ng-class="{disabled: '+ (option.attr('og-disabled') || 'false') +'}"></li>')
                      .html(groupHeader +'<a title="'+(option.attr('title')||'')+'"><span class="og-option">'+option.html()+'</span><span class="check-mark"><span class="fa fa-'+checkMark+'"></span></span></a>'));

              if (attr.inline!=undefined) {
                select.addClass('inline');
              }


              if (attr.multiple != undefined && attr.multiple && attr.solid != undefined) {
                solid = true;
              }

              angular.forEach(attr.$attr,function(attrName,attrKey){
                select.attr(attrName,attr[attrKey]);

              });

              if (select.attr('selected-text-format')==undefined) {
                select.attr('selected-text-format','count>0');
              }

              $compile(select[0])(scope);

              el.before(select);
              el.remove();

            },
            post: function(scope,el,attr){
              select.find('>button.btn').removeClass('btn btn-default').addClass('form-control');

              if (solid) {
                var toggle = select.find('.dropdown-toggle');
                $timeout(function(){
                  toggle.click();
                  select.addClass('solid');
                  select.removeClass('open');
                  $timeout(function(){
                    toggle.off('click');
                  });
                });
              }

              var butt = select.find('.filter-option');
              var setWidth = function(){
                butt.width(select.width() - 60);
              };

              var unwatch;
              $timeout(function(){
                setWidth(select.width());
                unwatch = scope.$watch(function(){
                  return select.width();
                },setWidth);
                scope.$on('$destroy',unwatch);
              });

            }
          };

        }
      };
    });

})();
