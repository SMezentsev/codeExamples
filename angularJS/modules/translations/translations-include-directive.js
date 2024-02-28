'use strict';

angular.module('app')
.directive('translationsInclude',function($rootScope,angularLoad,$localization,$translate,tmhDynamicLocale,AppConfig,$timeout){

    var hideTranslations;

    return {
      restrict: 'A',
      link: function(scope,el){

        hideTranslations = angular.element('<style type="text/css"></style>');
        el.prepend(hideTranslations);

        setTranslations();

        $rootScope.$on('$language:change',setTranslations);

      }
    };

    function setTranslations(){
      var lang = $localization.getLanguage();

      hideTranslations.text('[translate] { opacity:0; }');

      tmhDynamicLocale.set(lang);

      setTranslationsData(lang,window.translations);
//
//      angularLoad.loadScript('/i18n/translations/'+lang+'.js?'+AppConfig.scriptsCacheKey||new Date().getTime())
//        .then(function(){
//        })
//        .catch(function(){
//          setTranslationsData(lang);
//        })
//      ;

//      angularLoad.loadScript('/i18n/moment/'+lang+'.js?'+AppConfig.scriptsCacheKey||new Date().getTime());
    }

    function setTranslationsData(lang,data) {
      $timeout(function(){
        $localization.setLocale(lang,data||{});
        $timeout(function(){
          $translate.use(lang);
          $timeout(function(){
            hideTranslations.text('');
          });
        },10);
        $('[src^="/i18n/translations/"]').remove();
      });
    }
  });