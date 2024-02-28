'use strict';

angular.module('app')
  .provider('$localization',function $localizationProvider($translateProvider){
    var availableLanguages = [];
    return {
      setAvailableLanguages: function(langs){
        availableLanguages = langs;
      },
      getLanguage: function(){},
      setLanguage: function(){},
      setLocale: function(lang,langData){
        $translateProvider.translations(lang, langData);
      },
      $get: function(){
        return {
          getLanguage: this.getLanguage,
          setLanguage: this.setLanguage,
          availableLanguages: availableLanguages,
          setLocale: this.setLocale
        };
      }
    };
  });