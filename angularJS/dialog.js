'use strict';

angular.module('app')
.service('Dialog',function($modal,$timeout){

var _instance = null;

  return {
    alert: _alert,
    confirm: _confirm,
    loading: _loading,
    openTemplate: _openTemplate,
    open: _open,
    ckeckDialogInstance: _ckeckDialogInstance
  };


  function _alert(message,options) {

    options = options||{};

    options.size = options.size||'sm';

    options.template =
      '<div class="modal-body">'+
        translate(message)+
        '</div>'+
        '<div class="modal-footer">'+
        '<button class="btn" ng-click="dialog.hide()" tabindex="0">'+translate(options.ok||'dialogs.alertOk')+'</button>' +
        '</div>';

    options.controllerAs = 'dialog';

    var modal;

    options.controller = function(){
      this.hide = function(){
        modal.close();

      };
    };

    modal = $modal.open(options);

    var afterAlertCallback = function(){
      if (options.afterAlertCallback && typeof options.afterAlertCallback == 'function') {
       options.afterAlertCallback();
      }
    };

    modal.result.then(afterAlertCallback,afterAlertCallback);
  }

  function _loading(message) {
    message = message||'';
    if (message && message!='') {
      message = '&nbsp;'+translate(message);
    }
    var options = {
      backdrop: 'static',
      keyboard: false,
      size: 'auto',
      template: '<div class="modal-body">' +
        '<div class="loader-container"><span loader></span>'+message+'</div>' +
        '</div>'
    };

    return $modal.open(options);
  }


  function _ckeckDialogInstance() {

      if(_instance) {
          _instance.close();
      }
  }


  function _confirm(message,param1,param2){

    var options = {};

    if (typeof param1=='object' && param2==undefined) {
      options = param1;
    } else {
      if (typeof param2=='object') {
        options = param2;
      } else if (typeof param2 == 'function') {
        options.cancelCallback = param2;
      }
      options.confirmCallback = param1;
    }

    options.size = 'sm';

    options.template =
      '<div class="modal-body">'+
            translate(message)+
        '</div>'+
        '<div class="modal-footer">'+
          '<button class="btn btn-primary" ng-click="dialog.hide();'+ (options.confirmCallback && typeof options.confirmCallback == 'string' ? options.confirmCallback+'();':'')+'" tabindex="0">'+translate(options.yes||'dialogs.confirmYes')+'</button>' +
          '<button class="btn btn-default" ng-click="dialog.cancel();'+ (options.cancelCallback && typeof options.cancelCallback == 'string' ? options.cancelCallback+'();':'')+'" tabindex="0">'+translate(options.no||'dialogs.confirmNo')+'</button>' +

        '</div>'
      ;

    options.controllerAs = 'dialog';

    var modal;

    options.controller = function(){
      this.hide = function(){
        modal.close();
        if (options.confirmCallback && typeof options.confirmCallback == 'function') {
          options.confirmCallback();
        }
      };
      this.cancel = function(event){
        modal.dismiss();
        if (options.cancelCallback && typeof options.cancelCallback == 'function') {
          options.cancelCallback(event);
        }
      };
    };

    modal = $modal.open(options);

  }

    function _open(options) {

      options = options||{};
      options.backdrop = 'static';
      _ckeckDialogInstance();

      var template = options.template||'';

      if (options.header) {
        template +=
          '<div class="modal-header bg-'+(options.headerBg?options.headerBg:'primary')+'">' +
            '<div class="close" ng-click="dialog.cancel()" aria-label="Close"><span aria-hidden="true">✕</span></div>' +
            '<b class="modal-title">'+translate(options.header)+'</b>' +
          '</div>';
        og.del(options,'header');
      }

      if (options.contentUrl) {
        template +=
          '<div class="modal-body" ' + (options.attribute ? options.attribute : '') + '>' +
            '<div class="loader-container"><div loader></div></div>' +
            '<div modal-include ng-include="\''+options.contentUrl+'\'"></div>' +
          '</div>';

        og.del(options,'contentUrl');
      }



      options.template = template;

      options.controllerAs = 'dialog';

      var modal;

      options.controller = function(){
        this.cancel = function(event){
          modal.dismiss();
          if (options.cancelCallback && typeof options.cancelCallback == 'function') {
            options.cancelCallback(event);
          }
        };
      };



        _instance = modal = $modal.open(options);

        $timeout(function() {
            if (options.after) {
                options.after(options.scope);
            }
        });

      return modal;
    }


    function _openTemplate(url,options) {
    options = options||{};

    options.templateUrl = url;

    options.controllerAs = 'dialog';

    var modal;

    options.controller = function(){
      this.hide = function(){
        modal.close();
        if (options.confirmCallback && typeof options.confirmCallback == 'function') {
          options.confirmCallback();
        }
      };
      this.cancel = function(event){
        modal.dismiss();
        if (options.cancelCallback && typeof options.cancelCallback == 'function') {
          options.cancelCallback(event);
        }
      };
    };

    modal = $modal.open(options);

  }

})

.directive('modalInclude',function(){
  return {
    restrict:'A',
    link: function(scope,el){
      el.parent().find('.loader-container').remove();
    }
  };
})

;
