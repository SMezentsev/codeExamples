(function(){
  'use strict';

  angular.module('app')
    .service('Model', function (AppConfig,AuthAccess, Server, $q, $timeout,OgDataGrid, Inform, webStorage) {
      var resource = false;

      if(typeof window.projectConfig != 'undefined') {
        $.extend(AppConfig.models,  window.projectConfig.models);
      }

      angular.forEach(AppConfig.models,function (config,modelName){

        config = config||{};
        config.server = config.server || 'backendInner';
        config.resource = config.resource || pluralize(modelName.toLowerCase());
        config.envelope = config.envelope || config.resource;
        config.envelopeOne = config.envelope || pluralize(config.resource,1);
        config.post =  config.post || {};
        config.permissions =  config.permissions || false;
        config.fields = angular.extend({
          direct:[],
          format: {},
          expand: {},
          with: {},
          normalize:{},
          postnormalize:{}
        },config.fields || {});
        config.relations = config.relations || {};
        config.access = angular.extend({create: true, update: true, delete: true},config.access || {});
      });

      var _caches = {}, _ttl = 100000;

      var formatters = {
        date: function(real,arg){
          return moment(real,arg).toDate();
        }
      };
      var deformatters = {
        date: function(normal,arg){
          return moment(normal).format(arg);
        }
      };

      return _Model;

      function ModelInstance(config) {

      }

      function _Model(modelName, params) {

        if (modelName && AppConfig.models && AppConfig.models[modelName]) {

          var modelConfig = AppConfig.models[modelName];

          var
            _resetCache,
            _collectionCacheId,
            _with = [],
            _relation,
            _tc,
            _collection,
            _options = {};

          var pub = {
            name: modelName,
            config: modelConfig,
            options: function(options){
              angular.extend(_options,options);
              return pub;
            },
            with: function(setWith,setWithIn){
              var withData = [setWith];
              if (setWithIn) {
                withData.push(setWithIn);
              }
              _with.push(withData);

              return pub;
            },
            resetCache: function(setResetCache){
              _resetCache = setResetCache;
              return pub;
            },
            collection: function(object,variable,cacheId){
              _collection = [object,variable,cacheId];
              _collectionCacheId = cacheId;
              return pub;
            },
            normalize: function (realInstance) {

              var fields = modelConfig.fields;
              var normalInstance = new ModelInstance(modelConfig);

              ////////
              angular.forEach(fields.direct,function(directKey){
                normalInstance[directKey] = realInstance[directKey];
              });
              ////////
              angular.forEach(fields.normalize,function (rule, normalKey){
                if (typeof rule == 'string') {
                  normalInstance[normalKey] = realInstance[rule];
                } else if (typeof rule == 'function') {
                  normalInstance[normalKey] = rule(realInstance,AppConfig,AuthAccess);
                } else if (typeof rule == 'array') {
                  normalInstance[normalKey] = formatters[rule[0]](realInstance[rule[1]],rule[2]);
                }
              });
              ////////
              angular.forEach(fields.format,function(formatData,normalKey){
                var formatter = formatData[0];
                var realKey = formatData[1]||normalKey;
                if (realInstance[realKey]) {
                  normalInstance[normalKey] = formatters[formatter](realInstance[realKey],formatData.slice(2));
                }
              });
              ////////
              angular.forEach(fields.expand,function(expandModel,normalField){
                //if (fields.normalize[normalField]==undefined||fields.normalize[normalField]!==false) {
                var realField,collection=false;
                if (angular.isObject(expandModel)) {
                  realField = expandModel.field||normalField;
                  collection = !!expandModel.collection;
                  expandModel = expandModel.model;
                } else {
                  realField = normalField;
                }
                if (realInstance[realField]) {
                  if (collection) {
                    normalInstance[normalField] = [];
                    angular.forEach(realInstance[realField],function(realExpand){
                      normalInstance[normalField].push(_Model(expandModel).normalize(realExpand));
                    });
                  } else {
                    normalInstance[normalField] = _Model(expandModel).normalize(realInstance[realField]);
                  }
                }
                //}
              });
              ////////
              angular.forEach(_with,function(withData){
                var field = withData[0];
                var collection =withData[1];
                var withModel = fields.with[field];
                if (withModel) {
                  if (collection) {
                    normalInstance[field] = og.findInBy(collection,'uid',realInstance[withModel.by]);
                  } else {
                    _Model(withModel.model).getByPk(realInstance[withModel.by]).then(function(related){
                      normalInstance[field] = related;
                    });
                  }
                }
              });
              ////////
              $timeout(function(){
                angular.forEach(fields.postnormalize,function (rule, normalKey) {
                  if (typeof rule == 'string') {
                    normalInstance[normalKey] = normalInstance[rule];
                  } else if (typeof rule == 'function') {
                    normalInstance[normalKey] = rule(normalInstance);
                  }
                });
                angular.forEach(fields.postexpand,function(expandModel,normalField){
                  var realField;
                  if (angular.isArray(expandModel)) {
                    realField = expandModel[0];
                    expandModel = expandModel[1];
                  } else {
                    realField = normalField;
                  }
                  if (normalInstance[realField]) {
                    normalInstance[normalField] = _Model(expandModel).normalize(normalInstance[realField]);
                  }
                });
                ///////

//              angular.forEach(modelConfig.relations,function(relParams,relName){
//                normalInstance[relName] = function(linkTo){
//                  var relModel = _Model(relParams.model);
//                  if (relParam.hasOne) {
//                    linkTo = {};
//                    relModel.getOne(normalInstance)
//                  } else {
//                    linkTo = [];
//                  }
//                }
//              });
              });

              return normalInstance;
            },
            denormalize: function (normalInstance) {

              var fields = modelConfig.fields;
              var realInstance = {};
              ////////
              angular.forEach(fields.direct,function(directKey){
                realInstance[directKey] = normalInstance[directKey];
              });
              ////////
              angular.forEach(fields.denormalize,function (rule, realKey) {
                if (typeof rule == 'string') {
                  realInstance[realKey] = normalInstance[rule];
                } else if (typeof rule == 'function') {
                  realInstance[realKey] = rule(normalInstance);
                } else if (typeof rule == 'array') {
                  realInstance[realKey] = deformatters[rule[0]](normalInstance[rule[1]],rule[2]);
                }
              });
              ////////
              angular.forEach(fields.format,function (formatData, normalKey) {
                var formatter = formatData[0];
                var realKey = formatData[1]||normalKey;
                if (normalInstance[normalKey]) {
                  realInstance[realKey] = deformatters[formatter](normalInstance[normalKey],formatData[2]);
                }
              });
              ////////
              angular.forEach(fields.expand,function(expandModel,normalField){
                if (fields.denormalize[normalField]==undefined||fields.denormalize[normalField]!==false) {
                  var realField,collection=false;
                  if (angular.isObject(expandModel)) {
                    realField = expandModel.field||normalField;
                    collection = !!expandModel.collection;
                    expandModel = expandModel.model;
                  } else {
                    realField = normalField;
                  }
                  if (normalInstance[normalField]) {
                    if (collection) {
                      realInstance[realField] = [];
                      angular.forEach(normalInstance[normalField],function(normalExpand){
                        realInstance[realField].push(_Model(expandModel).denormalize(normalExpand));
                      });
                    } else {
                      realInstance[realField] = _Model(expandModel).denormalize(normalInstance[normalField]);
                    }
                  }
                }
              });


              return realInstance;
            },
            relation: function(relationModel){
              _relation = _Model(relationModel);
              return pub;
            },
            getByPk: function(uid,params){
              params = params || {};
              var defer = $q.defer();

              var cache = _cacheRequest(modelName,'pk:'+uid,params,_resetCache);

              var relation = _relation ? '/'+_relation.config.resource : '';

              Server(modelConfig.server, 'get', modelConfig.resource +'/'+uid+relation, {cache:cache,params: params})
                .then(function (data) {
                  var result;
                  if (_relation) {
                    data = data.data[_relation.config.envelope];
                    if (angular.isArray(data)) {
                      result = og.map(data,function(item){
                        return _relation.normalize(item);
                      });
                    } else {
                      result = _relation.normalize(data);
                    }
                  } else {
                    data = data.data;
                    result = pub.normalize(data);
                  }
                  defer.resolve(result);
                }, defer.reject);
              return defer.promise;
            },
            datagrid: function(object,variable,params,page) {

              var data = {};

              if(params && typeof params.replace != 'undefined') {
                $.each(params.replace, function(key, value) {
                  resource = modelConfig.resource.replace(value.param, value.value);
                });
              }

              $timeout(function() {

                if(params && typeof params.replace != 'undefined') {
                  delete params.replace;
                }

                data = {
                  object: object,
                  variable: variable,
                  params: params,
                  page: page,
                  server: modelConfig.server,
                  resource: resource ? resource : modelConfig.resource,
                  envelope: modelConfig.envelope,
                  normalize: pub.normalize,
                  urlPagination: _options.urlPagination
                };

                if(resource) {
                  resource = false;
                }

                if(typeof modelConfig.permissions != 'undefined') {
                  $.extend(data, {permissions:modelConfig.permissions})
                }

                OgDataGrid(data);
              });

            },

            getAll: function (params) {

              params = params || {};
              var defer = $q.defer();
              var normalizedCollection = [];

              var cache = _cacheRequest(modelName,'all',params,_resetCache,_collection?_collection[2]:undefined);

              _getCollection();

              return defer.promise;

              function _getCollection(page) {

                if (page) {
                  params.page = page;
                }

                Server(modelConfig.server, 'get', modelConfig.resource, {cache: cache,params: params})
                  .then(_normalizeCollection, defer.reject);

              }

              function _normalizeCollection(data) {
                var collection = modelConfig.envelope ? data.data[modelConfig.envelope] : data.data;
                angular.forEach(collection, function (instance) {
                  normalizedCollection.push(pub.normalize(instance));
                });
                var meta = data.data._meta;
                if (meta && meta.pageCount>meta.currentPage) {
                  _getCollection(meta.currentPage+1);
                } else {
                  _resolveCollection();
                }
              }

              function _resolveCollection(){
                $timeout(function () {
                  if (_collection && _collection[0] && _collection[1]) {
                    _collection[0][_collection[1]] = normalizedCollection;
                  }
                  defer.resolve(normalizedCollection);
                });
              }

              function checkUrlAccess(url) {

                var user = webStorage.get(AppConfig.prefix + 'user');
                var access = false;

                if(url == 'logout' || url == 'auth') {
                  access = true;
                }

                if(!access && AppConfig && typeof AppConfig.access.urlAccess != 'undefined') {

                  var rules;

                  if(AppConfig.access.urlAccess[url]) {

                    rules = AppConfig.access.urlAccess[url].access.permissions;

                    angular.forEach(user.permissions, function(perm){
                      if((rules.indexOf(perm) + 1)) {
                        access = true;
                      }

                    });

                  } else {

                    access = true;
                  }

                }

                return access;

              }

            },
            tc: function(tc){
              _tc = tc;
              return pub;
            },
            save: function (normalInstance,callback) {

              console.log('normalInstance',normalInstance)
              var defer = $q.defer();
              var id = normalInstance.uid||normalInstance.id;
              var method =  id ? 'put' : 'post';
              var url = id ? modelConfig.resource + '/' + id : modelConfig.resource;
              var params = {data: pub.denormalize(normalInstance)};

              console.log('ididididid',id)

              if(modelConfig.post) {
                $.extend(params,{params:{expand:modelConfig.post.expand}});
              }

              if(callback) {
                $.extend(params,{callback:callback});
              }

              Server(modelConfig.server, method, url, params)
                .then(function (data) {
                  angular.extend(normalInstance,pub.normalize(data.data));
                  if (_collection && _collection[0] && _collection[1]) {

                    var by = false;
                    if(normalInstance.id) {
                      by = 'id';
                    } else if(!by && normalInstance.uid) {
                      by = 'uid';
                    }

                    og.collection(_collection[0],_collection[1]).save(normalInstance, by);
                    if (_collection[2]) {
                      og.del(_caches,_collection[2]);
                    }
                  }
                  //Inform((_tc||'model')+'.'+modelName+'-'+(normalInstance.uid?'updated':'created'),'success');
                  Inform((_tc||'models')+'.Model-updated','success');
                  defer.resolve(normalInstance);
                }, defer.reject);
              return defer.promise;
            },
            delete: function (instance) {
              var defer = $q.defer();
              Server(modelConfig.server, 'delete', modelConfig.resource + '/' + instance.uid)
                .then(function(){
                  if (_collection && _collection[0] && _collection[1]) {
                    og.collection(_collection[0],_collection[1]).delete(instance);
                    if (_collection[2]) {
                      og.del(_caches,_collection[2]);
                    }
                  }
                  defer.resolve()
                });
              return defer.promise;
            }
          };

          return pub;

        }

      }

      function _cacheRequest(model,scenario,params,reset,collectionCacheId) {

        params = params||{};
        var cacheId = collectionCacheId||model+':'+scenario+':'+angular.toJson(params,false);

        if (collectionCacheId)
          _cacheRequest(model,scenario,params,reset);

        var cache;
        if (!reset) {
          cache = _checkCache(cacheId);
          _caches[cacheId] = new Date().getTime();
        } else {
          cache = false;
          og.delInBy(_caches,cacheId);
        }

        //console.log('cache',cacheId,cache?'y':'n');

        return cache;
      }

      function _checkCache(cacheId) {
        var cache = false;

        if (_caches[cacheId]) {
          if ((new Date().getTime()-_caches[cacheId])<_ttl) {
            cache = true;
          } else {
            og.delInBy(_caches,cacheId);
          }
        }

        return cache;
      }
    })
  ;

})();
