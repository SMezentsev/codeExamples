'use strict';

angular.module('app')
  .service('Server',function($http,$q,AppConfig,AuthAccess,Inform,$rootScope,Dialog,$location,$timeout,webStorage){

    var alertShow = false;
    var modal = false;

    $rootScope.notAuth = function() {
      //$location.path('/login');
      alertShow = false;
      modal.close();
    };


    function getServerConfig(server) {
      var connectionString = AppConfig.servers[server].url;
      var config = {};
      var parts = connectionString.split(';');
      config.root = parts.shift();
      config.root = config.root.replace(/\/$/,'')+'/';
      if (parts.length) {
        angular.forEach(parts,function(part){
          var keyvalue = part.split('=');
          config[keyvalue[0]] = keyvalue[1];
        });
      }
      return config;
    }

    return function(server,method,url,options) {

      var defer = $q.defer();

//        if(!checkUrlAccess(url)) {
//            return defer.promise;
//        }

      if(server === 'backend') {
        server = 'backendInner';
      }

      options = options ||{};

      var config = getServerConfig(server);

      if(typeof AppConfig.models[url] != 'undefined') {
        if(typeof AppConfig.models[url].params != 'undefined') {

          var modelParams = angular.copy(AppConfig.models[url].params);

          $.extend(modelParams,options.params); //перезаписываем параметры если таковые были переданы из вне
          $.extend(options.params, modelParams);
        }
      }

      var errorCallback = options.callback||function(error){};
      var config = getServerConfig(server);

      var token = config.debugToken ? config.debugToken : AuthAccess.token;
      if (token) {
        options.params = options.params||{};
        if (options.noAccessToken) {
          og.del(options,'noAccessToken');
        } else {
          options.params['access-token'] = token;
        }
      }

      options = options||{};

      options.method = method;
      options.url = url.replace(/^\//,'');
      //$.extend(options.params, {rand:og.random(15)});

      if (options.params) {
        var params = $.param(options.params);
        og.del(options,'params');
        if (params!='') {
          options.url += '?'+params;
        }
      }

      options.url = config.root + options.url;

      if(typeof options.data != 'undefined' &&  typeof options.data.headers != 'undefined') {

        $.extend(options, {headers:options.data.headers})
      }

      $http(options).then(function(data){

        $rootScope.$broadcast('submitFinish',{});
        defer.resolve(data);
      },function(error) {

        $rootScope.$broadcast('submitFinish',{});
        defer.reject(errorCallback(error));

        if(error.data && (error.data.status == 400 || error.data.status == 404)) {
          Inform(error.data.message,{
            icon: 'thumbs-down',
            type: 'danger',
            duration: 4000
          });
        }

        if(error.data && error.status == 401 && (error.data.code == 400 || error.data.code == 401 || error.data.code == 402)) {
          Inform(error.data.message,{
            icon: 'thumbs-down',
            type: 'danger'
          });

        } else if (!error.data) {

          Inform('Server error',{
            icon: 'thumbs-down',
            type: 'danger'
          });

        } else if (error.status==406 || error.status==405) {
          Inform(error.data.message,{
            type: 'danger'
          })
        } else if(error.data.code == 6002) {
          Inform(error.data.message,{
            icon: 'thumbs-down',
            type: 'danger'
          });
        }

      });

      return defer.promise;
    }
  });


