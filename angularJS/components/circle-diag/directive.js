(function(){
  'use strict';

  angular.module('app')
  .directive('circleDiag',function($timeout){
    return {
      restrict:'A',
      template: '<div class="circle-diag">'+
        '<div class="circle-diag-bg bg-indication-normal"><div class="circle-diag-bg-second"></div></div>'+
        '<div class="circle-diag-hold first-half">' +
          '<div class="circle-diag-pie bg-indication-normal" ></div>' +
        '</div>' +
        '<div class="circle-diag-hold second-half">' +
          '<div class="circle-diag-pie  bg-indication-normal" ></div>' +
        '</div>'+
      '<div class="circle-diag-inner text-indication-normal">' +
        '<div class="circle-diag-value">&nbsp;</div>'+
        '<div class="circle-diag-caption">баллов</div>'+
      '</div>'+
      '</div>',
      link: function(scope,el,attr){

        if (attr.caption!=undefined) {
          el.find('.circle-diag-caption').text(translate(attr.caption));
        } else if (attr.pluralize) {
          el.find('.circle-diag-caption').text(translate(attr.caption));
        }

        var valueEl = el.find('.circle-diag-value');
        var height = el.find('.circle-diag').height();

        var hold1 = el.find('.first-half');
        var hold2 = el.find('.second-half');

        var pie1 = hold1.find('.circle-diag-pie');
        var pie2 = hold2.find('.circle-diag-pie');

        var unwatch = scope.$watch(attr.circleDiag,function(value){
          value = parseInt(value||0);
          valueEl.text(value);
          var angle1,angle2;
          if (value<50) {
            angle1 = 'rotate('+(value*360/100)+'deg)';
            angle2 = 'rotate(0deg)';
          } else {
            angle1 = 'rotate(180deg)';
            angle2 = 'rotate('+((value-50)*360/100)+'deg)';
          }
          pie1.css({
            '-webkit-transform': angle1,
            '-moz-transform': angle1,
            '-o-transform': angle1,
            'transform': angle1
          });
          pie2.css({
            '-webkit-transform': angle2,
            '-moz-transform': angle2,
            '-o-transform': angle2,
            'transform': angle2
          });
        });

        scope.$on('$destroy',function(){
          unwatch();
        });

        var rectRight = 'rect(0,'+height+'px,'+height+'px,'+height/2+'px)';
        var rectLeft = 'rect(0,'+height/2+'px,'+height+'px,0)';

        hold1.css('clip',rectRight);
        pie1.css('clip',rectLeft);
        hold2.css('clip',rectLeft);
        pie2.css('clip',rectRight);

      }
    };
  });
})();