// Create a jquery plugin that prints the given element.
jQuery.fn.print = function(){

  // NOTE: We are trimming the jQuery collection down to the
  // first element in the collection.
  if (this.size() > 1){
    this.eq( 0 ).print();
    return;
  } else if (!this.size()){
    return;
  }

  // ASSERT: At this point, we know that the current jQuery
  // collection (as defined by THIS), contains only one
  // printable element.

  // Create a random name for the print frame.
  var strFrameName = ("printer-" + (new Date()).getTime());

  // Create an iFrame with the new name.
  var jFrame = $( "<iframe name='" + strFrameName + "'></iframe>" );

  // Hide the frame (sort of) and attach to the body.
  jFrame
    .css( "width", "1px" )
    .css( "height", "1px" )
    .css( "position", "absolute" )
    .css( "left", "-9999px" )
    .appendTo( $( "body:first" ) )
  ;

  // Get a FRAMES reference to the new frame.
  var objFrame = window.frames[ strFrameName ];

  // Get a reference to the DOM in the new frame.
  var objDoc = objFrame.document;
  var content = this.clone();

  // Grab all the style tags and copy to the new
  // document so that we capture look and feel of
  // the current document.

  // Create a temp document DIV to hold the style tags.
  // This is the only way I could find to get the style
  // tags into IE.
  var includes = $( "<div>" );
  includes.append($('base').clone());

  var selector = 'style:not([no-print]):not([type="print"]),link[rel="stylesheet"]';

  content.find(selector).remove();

  $(selector).each(function(){
    includes.append($(this).clone());
  });

  content.find('style[type="print"]').attr('type','text/css');
  content.find('[no-print]').remove();

  content.find('.print-on-new-page:not(:last)').each(function(){
    $(this).after($('<div style="clear:both;page-break-after: always;"></div>'));
  });

  setTimeout(function(){

    // Write the HTML for the document. In this, we will
    // write out the HTML of the current element.
    objDoc.open();
    objDoc.write( "<!DOCTYPE html>" );
    objDoc.write( "<html>" );
    objDoc.write( "<body>" );
    objDoc.write( "<head>" );
    objDoc.write( "<title>" );
    objDoc.write( document.title );
    objDoc.write( "</title>" );
    objDoc.write( includes.html() );
    objDoc.write( "</head>" );
    objDoc.write( content.html() );
    objDoc.write( "</body>" );
    objDoc.write( "</html>" );
    objDoc.close();

    $(objFrame).load(function(){
      // Print the document.
      objFrame.focus();
      objFrame.print();
      jFrame.remove();
    });

  },100);


};