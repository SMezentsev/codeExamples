function translate(key,data) {
  data = data||{};
  if (key==undefined)
    return '';
  var parts = key.split('.',2);

  if (parts[1] && window.translations[parts[0]] && window.translations[parts[0]][parts[1]]) {
    var translation = window.translations[parts[0]][parts[1]];
    for(var prop in data) {
      if(data.hasOwnProperty(prop)) {
        translation = translation.replace('{{'+prop+'}}',data[prop]).replace('{{ '+prop+' }}',data[prop]);
      }
    }
    translation = translation.replace(/(.*)\{\{(.*?)\}\}(.*)/g,'$1 $3');

    return translation;
  } else {
    return key;
  }
}
