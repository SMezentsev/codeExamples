(function(){
  'use strict';

  angular.module('app')

    .service('Inform',function(notify){
      return function(message,options){
        if (typeof options =='string') {
          options = {
            type: options
          };
        } else {
          options = options||{};
        }

        options.type = options.type||'info';

        options.classes = 'alert-'+options.type;
        og.del(options,'type');

        options.duration = options.duration||2000;
        options.position = options.position||'right';

        options.message = translate(message,options.data);

        options.icon = options.icon||'info-circle';

        options.messageTemplate = '<span><i class="icon fa fa-'+options.icon+'"></i>&nbsp;&nbsp;'+options.message+'</span>';
        og.del(options,'message');

        notify(options);
      }
    });

})();