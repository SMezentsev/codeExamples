'use strict';

//////////// to access module //////////////////////

angular.module('app')
.provider('AuthAccess',function AuthAccessProvider($stateProvider, $urlRouterProvider, $locationProvider, $httpProvider){

    var access = this;

    this.init = function(config){
        angular.extend(access,config);

        access.roles = buildRoles(access.roles);

        setHttpInterceptor();
    };


    this.$get = function(){
        return access;
    };



    function setHttpInterceptor() {

        access.loginUrl = '/';

        $httpProvider.interceptors.push(function($q, $location) {

            var loginUrl = access.loginUrl||'/login';

            return {
                'responseError': function(response) {
                    if(response.status === 401 || response.status === 403) {
                      if ($location.path()!=loginUrl) {

                          //$rootScope.$broadcast('unauth', {callback:function() {
                              //$location.path(access.loginUrl||'/login');
                          //}});

                      }
                    }
                    return $q.reject(response);
                }
            };

        });
    }

    function buildRoles(roles){

        var allRoles = angular.extend({
          guest: {homeState:'index'},
          user: {homeState:'index'}
        },roles);

        var userRoles = {};

        angular.forEach(allRoles,function(data,role){
            userRoles[role] = angular.extend({
                key: role
            },data||{});
        });

        return userRoles;
    }

})

.run(function($rootScope,$state,Auth){

    $rootScope.$on("$stateChangeStart", function (event, toState, toParams, fromState, fromParams) {

        var strict = false;
        var access = false;

        if (toState.access) {
          strict = true;

          if (toState.access=='auth' && Auth.isLoggedIn()) {
            access = true;

          } else {

            if (toState.access.roles && Auth.checkRoles(toState.access.roles)) {
              access = true;
            }

            if (toState.access.permissions && Auth.checkPermissions(toState.access.permissions)) {
              access = true;
            }
          }
        }

        if (strict && !access) {
            $rootScope.error = "Seems like you tried accessing a route you don't have access to...";

            event.preventDefault();

            if(Auth.isLoggedIn()) {
                $state.go('error');
            } else {
              Auth.returnState = $state.current;
              $rootScope.error = null;
              $state.go('login');
            }
        }

    });

    $rootScope.accessAuth = function(){
      return Auth.isLoggedIn();
    };

    $rootScope.accessRoles = function(roles){
      return Auth.checkRoles(roles);
    };

    $rootScope.accessPermissions = function(permissions){
      return Auth.checkPermissions(permissions);
    };
})

.service('AuthControl', function(AuthAccess,$http, webStorage, Server,$location,$state,$rootScope,Model,Inform,AppConfig){
        var currentUser = webStorage.get(AppConfig.prefix + 'user');

        var pub = {

            ping: function() {
                 Server('backendOuter','post','auth',{
                    noAccessToken: true,
                    data: user
                })
                .then(function(data){

                    AuthAccess.outerAccess.token = data.data.token.id;
                },error||function(){});

            },
            inform: function() {

                //Inform('NOT YET ON SERVER',{position:'center',type:'warning',icon:'meh-o'})
            }

        };

        return pub;

    })
.service('Auth', function(AuthAccess,$http, webStorage, Server,$location,$state,$rootScope,Model,Inform,AppConfig,Dialog,$interval){

    var v = 3;

    var currentUser = webStorage.get(AppConfig.prefix + 'user');
    var outerToken = webStorage.get(AppConfig.prefix + 'outerToken');

    $rootScope.pingServer = function(server, token) {

        if(token) {
            var connectionString = AppConfig.servers[server].url;
            $http({
                cache: false,
                method: "get",
                url: connectionString + 'rf/ping?access-token=' + token
            }).then(function(data){

//                        if(_getOuterAuthStatus(true)) {
                    if(!$rootScope.outerAuth) {
                        $rootScope.$broadcast('outerAuth:change',{message:'В сети'});
                    }


                    $rootScope.outerAuth = true;

                    webStorage.add(AppConfig.prefix + 'outerAuth',true);
//                        } else {
//                            $rootScope.outerAuth = false;
//                        }
                },function(error) {


                    if (error.status==404 || error.status==401 || error.status==-1) {
                        if($rootScope.outerAuth) {
                            $rootScope.$broadcast('outerAuth:change',{message:'Не в сети'});
                        }
                        $rootScope.outerAuth = false;
                        if(webStorage.get(AppConfig.prefix + 'outerToken')) {
                            //webStorage.remove('outerToken');
                        }
                        $rootScope.outerAuth = false;
                        webStorage.add(AppConfig.prefix + 'outerAuth',false);

                        $rootScope.$broadcast('ExtraUnLog');
                    }
                });

        }
    };

    if (!currentUser || currentUser.v!=v) {
      setCurrentUser(null);
      $location.path('/login');
    } else {

      AuthAccess.token = currentUser.token;
      if(typeof AppConfig.servers.backendInner != 'undefined' && AppConfig.servers.backendInner.url != '') {
          AuthAccess.innerAccess = {
              url : AppConfig.servers.backendInner.url,
              token: currentUser.token
          };
      } else {
          console.log('Wrong parameters backendInner');
      }

      if(typeof AppConfig.servers.backendOuter != 'undefined' && AppConfig.servers.backendOuter.url != '') {
          AuthAccess.outerAccess = {
              url : AppConfig.servers.backendOuter.url,
              token: currentUser.token
          };
      } else {
          console.log('Wrong parameters backendOuter');
      }

    }

    $rootScope.getPermissions = function(permission) {

        var user = webStorage.get(AppConfig.prefix + 'user');
        $rootScope.permissions = user.permissions;

        var perm = og.findInArray($rootScope.permissions,permission);

        if(perm) {
            return true;
        } else {
            return false;
        }
    };

    function setCurrentUser(AuthUser,extend) {
      if (!AuthUser) {
        currentUser = null;
        webStorage.remove(AppConfig.prefix + 'user');
        AuthAccess.token = null;
      } else {
        currentUser = angular.extend(AuthUser,extend);
        currentUser.roles = currentUser.roles||[];
        currentUser.roles.push('user');
        currentUser.v = v;
        webStorage.add(AppConfig.prefix + 'user',currentUser);
        AuthAccess.token = currentUser.token;
      }
      $rootScope.$broadcast('AuthAccess:change');
    }

    function getUser() {

      var user = currentUser ? currentUser : {
        name: 'Guest',
        roles: [],
        permissions: []
      };

      return user;
    }

    function checkAccess(access) {

      var allow =false;
      if (access=='auth') {
        if (isLoggedIn()) {
          allow = true;
        }
      } else {
        if (access.roles && checkRoles(access.roles)) {
          allow = true;
        }

        if (access.permissions && checkPermissions(access.permissions)) {
          allow = true;
        }

        if(access.mask && access.mask != '' && checkPermissionsByMask(access.mask)) {
            allow = true;
        }

      }

      return allow;
    }

    function isLoggedIn() {
        return !!currentUser;
    }

    function checkRoles(roles) {
      roles = roles.split(',');
      var allow = false;
      angular.forEach(roles,function(role){
        if (!allow && $.inArray(role,getUser().roles)>-1)
          allow = true;
      });
      return allow;
    }

    function checkPermissions(permissions) {
        $.arrayIntersect = function(a, b)
        {
            return $.grep(a, function(i)
            {
                return $.inArray(i, b) > -1;
            });
        };

        permissions = permissions.replace(' ', '').replace(' ', '').split(',');


        var allow = false;

        if(!allow && $.arrayIntersect(permissions,getUser().permissions).length) {
            allow = true;
        }

        return allow;
    }

    function checkPermissionsByMask(mask) {
        var allow = false;
        angular.forEach(getUser().permissions,function(permission){

            if (!allow && (permission.indexOf(mask) + 1)) {
                allow = true;
            }
        });
        return allow;
    }

    function outerAuthLogin(user) {

        var connectionString = AppConfig.servers['backendOuter'].url;

        var result = false;

        $http({
            noAccessToken: true,
            data: user,
            cache: false,
            method: "POST",
            url: connectionString + 'auth'
        }).then(function(data) {

//                if(_getOuterAuthStatus()) {
                    $.extend(AuthAccess, { outerAccess:{ token :data.data.token.id}});
                    //var currentUser = webStorage.get('user');
                    webStorage.add(AppConfig.prefix + 'outerToken',AuthAccess.outerAccess.token);
                    webStorage.add(AppConfig.prefix + 'outerAuth',true);
                    $rootScope.outerAuth = true;

                    $rootScope.$broadcast('outerAuth:change',{message:'В сети'});


//                }
            },function(error) {

            });

        return result;

    };

    function _getOuterAuthStatus(ping) {
//            if(ping) {
//                webStorage.add('outerAuth',true);
//            }
        return webStorage.get(AppConfig.prefix + 'outerAuth');
    }

    function _outerAuthSwitch() {
        var auth = webStorage.get(AppConfig.prefix + 'outerAuth');

        if(auth) {
            webStorage.add(AppConfig.prefix + 'outerAuth',false);
            $rootScope.outerAuth = false;
        } else {
            webStorage.add(AppConfig.prefix + 'outerAuth',true);
            $rootScope.outerAuth = true;
        }

    }


    var extraUnlogCount = false;

    var pub = {
        returnUrl: null,
        check: checkAccess,
        checkRoles: checkRoles,
        checkPermissions: checkPermissions,
//        checkLevel: checkLevel,
//        checkFunction: checkFunction,
//        getRole: getRole,
        getUser: getUser,
        isLoggedIn: isLoggedIn,
        register: function(user, success, error) {
          Server('backendInner','post','register',null,{
            data: user
          })
          .then(function(){

          },error);
        },
        outerAuthSwitch: function() {
            _outerAuthSwitch();
        },
        loginOuter: function(user, success, error, fail, options) {
            return Server('backendOuter','post','auth',{
                noAccessToken: true,
                data: user
            })
                .then(function(data){

                    $.extend(AuthAccess, {outerAccess:{ token :data.data.token.id}});

                    webStorage.add(AppConfig.prefix + 'outerToken',AuthAccess.outerAccess.token);
                },error||function(){});
        },

        extraUnLog: function() {


            if(extraUnlogCount) return false;

            AuthAccess.outerAccess.token = false;
            var alertShow = true;

            webStorage.remove(AppConfig.prefix + 'user');
            webStorage.remove(AppConfig.prefix + 'outerAuth');
            webStorage.remove(AppConfig.prefix + 'outerToken');
            webStorage.clear();
            AuthAccess.token = null;
            extraUnlogCount = true;
            var modal = Dialog.open({
                header: 'Ошибка авторизации',
                headerBg:'danger',
                contentUrl: 'app/states/system/not-auth.html',
                after: function() {
                    $location.path('/login');
                }
            });
        },

        login: function(user, success, error, fail, options) {
          var backend = 'backendInner';

          if(typeof options != 'undefined') {
              backend = options.server;
          }

          if(typeof options != 'undefined' && typeof options.force != 'undefined') {

              $.extend(user, {force: true});
          }

          return Server(backend,'post','auth',{
              noAccessToken: true,
              data: user,
              callback: error
          })
          .then(function(data){

              setCurrentUser(
                Model('AuthUser').normalize(data.data.user),
                {
                  token: data.data.token.id,
                  roles: og.map(data.data.roles,function(   role){
                    return role.name;
                  }),
                  permissions: og.map(data.data.permissions,function(permission){
                    return permission.name;
                  }),
                  permissionsAll: data.data.permissions
                }
              );
              $rootScope.permissions = data.data.permissions;
              var stdRole = AuthAccess.roles[currentUser.roles[0]];
              stdRole = stdRole||{};

              if (pub.returnUrl) {
                $location.path(pub.returnUrl);
                pub.returnUrl = null;
              } else if (stdRole.homeUrl) {
                $location.path(stdRole.homeUrl);
              } else if (stdRole.homeState){
                $state.go(stdRole.homeState);
              } else {
                $location.path('/');
              }
              $location.replace();
              $rootScope.AuthUser = getUser();
              if (success) {

                  if(typeof AppConfig.servers.doubleAuth != 'undefined') {
                      //if(!outerAuthLogin(user)) {
                      if((typeof AuthAccess.outerAccess != 'undefined' && !AuthAccess.outerAccess.token)
                          || (typeof AuthAccess.outerAccess == 'undefined')
                          ) {
                          outerAuthLogin(user);

//                          var authAttempt = $interval(function() {
//                              if(AuthAccess.outerAccess) {
//                                  $interval.cancel(authAttempt);
//                                  var outerToken = webStorage.get(AppConfig.prefix +'outerToken');
//                                  if(outerToken) {
//                                      $rootScope.$broadcast('outerAuth:change',{message:'В сети'});

//                                      //$rootScope.outerAuth = true;
//
//                                      $rootScope.pingServer('backendOuter', AuthAccess.outerAccess.token);
//
//
//                                      if(typeof AppConfig.servers.doubleAuth != 'undefined') {
//
//                                          $rootScope.pingServer('backendOuter', AuthAccess.outerAccess.token);
//                                          var ping = $interval(function(){
//                                              if(!AuthAccess.outerAccess.token) {
//                                                  $interval.cancel(ping);
//                                               }
//                                              $rootScope.pingServer('backendOuter', AuthAccess.outerAccess.token);
//                                          },AppConfig.servers.pingInterval||3000);
//                                      }
//
//                                  }
//                              }
//
//
//                          },1000)
                      };

                  }

                  success(user);
              }

          },error||function(){})
          ;

        },
        restore: function(data, success, error) {

          Inform('NOT YET ON SERVER',{position:'center',type:'warning',icon:'meh-o'})

//          Server('backend','post','auth',{
//            data: user
//          })
//            .then(function(data){
//
//              var AuthUser = Model('AuthUser').normalize(data.data.user);
//
//              setUser(AuthUser,data.data.token,data.data.functions);
//
//              var role = pub.getRole();
//              if (pub.returnUrl) {
//                $location.path(pub.returnUrl);
//                pub.returnUrl = null;
//              } else if (role.homeUrl) {
//                $location.path(role.homeUrl);
//              } else if (role.homeState){
//                $state.go(role.homeState);
//              } else {
//                $location.path('/');
//              }
//              $location.replace();
//
//              if (success)
//                success(user);
//            },error||function(){});
        },
        logout: function(success, error, options) {

            $interval.cancel($rootScope.pingStart);
           var backend = 'backendInner';
           extraUnlogCount = false;

           if(typeof options != 'undefined') {
                backend = options.server;
           }

            Server(backend,'get','logout')
                .then(function(){
                    logout(success);
                },function() {
                    logout(success);
                });
        }

    };

    function logout(success) {
        setCurrentUser(null);

        $location.path('/');

        $location.replace();

        if (success) {
            success();
            if(AuthAccess.outerAccess && typeof AuthAccess.outerAccess.token != 'undefined') {
                AuthAccess.outerAccess.token = false;

            }
            $location.path('/login');

        }

    }

    return pub;
})

.directive('accessAuth', function(Auth) {
    return {
        restrict: 'A',
        link: function(scope,el,attr) {


            var prevDisp = el.css('display');

            updateCSS();

            scope.$on('AuthAccess:change',updateCSS);

            function checkAuth() {
              return attr.accessAuth=='false'
                ? !Auth.isLoggedIn()
                : Auth.isLoggedIn();
            }

            function updateCSS() {
                if(checkAuth())
                    el.css('display', prevDisp);
                else
                    el.css('display', 'none');
            }
        }
    };
})


.directive('accessRole', function(Auth) {
    return {
        restrict: 'A',
        link: function(scope, el, attr) {

          var prevDisp = el.css('display');

          updateCSS();

          scope.$on('AuthAccess:change',updateCSS);

          function updateCSS() {
              if(!Auth.checkRoles(attr.accessRole))
                  el.css('display', 'none');
              else
                  el.css('display', prevDisp);
          }
        }
    };
})
.directive('accessPermissions', function(Auth) {
    return {
        restrict: 'A',
        link: function(scope, el, attr) {

          var prevDisp = el.css('display');

          updateCSS();

          scope.$on('AuthAccess:change',updateCSS);
          function updateCSS() {
              if(!Auth.checkPermissions(attr.accessPermissions))
                  el.css('display', 'none');
              else
                  el.css('display', prevDisp);
          }
        }
    };
});

angular.module('app')
.config(function(AppConfig,AuthAccessProvider){
    AuthAccessProvider.init(AppConfig.access||{});
});
