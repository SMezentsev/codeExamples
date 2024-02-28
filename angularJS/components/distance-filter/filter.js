(function(){
  'use strict';

  angular.module('app')
  .filter('distance',function(){
    var conversionKey = {
      m: {
        km: 0.001,
        m:1
      },
      km: {
        km:1,
        m: 1000
        //downgrade:[1000,'m']
      }
    };

    return function (distance, options) {
      options = options||{};
      options.from = options.from||'m';
      options.to = options.to||'km';

      var filtered = parseFloat((distance * conversionKey[options.from][options.to]));
      if (options.accuracy!==undefined) {
        filtered = filtered.toFixed(options.accuracy);
        if (!options.strict && filtered==Math.floor(filtered)) {
          filtered = Math.floor(filtered);
        }
        if (options.downgrade) {

        }
      }
      return filtered + translate('common.distance-'+options.to);
    }
  });

})();