'use strict';

window.translations = {};
angular.module('app')
.config(function($translateProvider,$localizationProvider,tmhDynamicLocaleProvider,AppConfig,webStorageProvider,$provide){

  var webStorage = webStorageProvider.$get();

  $localizationProvider.setAvailableLanguages(AppConfig.languages);

  var language = (webStorage.get('language')||AppConfig.setLanguage||
    window.navigator.userLanguage || window.navigator.language||AppConfig.languages[0]).substring(0,2);

  webStorage.add('language',language);

  $localizationProvider.getLanguage = function(){
    return language;
  };

  $localizationProvider.setLanguage = function(lang){
    language = lang;
    webStorage.add('language',language);
  };

  tmhDynamicLocaleProvider.localeLocationPattern('/i18n/locale/angular-locale_{{locale}}.js?'+AppConfig.scriptsCacheKey||new Date().getTime());

  //$translateProvider.directivePriority(1000);

})
  .run(function(){
    $('[ng-app="bootstrap"]').remove();
  })
;