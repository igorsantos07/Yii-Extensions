/*
 * jquery.tinymce.js is a plugin for integrating TinyMCE into a webpage, done
 * specially for the ETinyMce extension for Yii. It could be used standalone too
 *
 * Author: MetaYii
 */
(function($) {
   var arrTinyMceMode = new Array();
   var boolTinyMceCookies = false;

   // options is an associative array
   // mode can be 'text' or 'html'.
   // cookies is boolean
   $.fn.tinyMCE = function(options, mode, cookies)
   {
      var id = this.attr('id');

      tinymce.EditorManager.init(options);
      arrTinyMceMode[id] = mode;
      boolTinyMceCookies = cookies;
      if (boolTinyMceCookies) {
         tinymce.util.Cookie.set(id+'_editorMode', arrTinyMceMode[id]);
      }
   }

   // mode must be 'text' or 'html'
   $.fn.setModeTinyMCE = function(mode)
   {
      var id = this.attr('id');
      switch(mode) {         
         case 'html':
            tinymce.EditorManager.execCommand('mceAddControl', false, id);
            arrTinyMceMode[id] = 'html';
            break;

         case 'text':
            tinymce.EditorManager.execCommand('mceRemoveControl', false, id);
            arrTinyMceMode[id] = 'text';
            break;
      }
      if (boolTinyMceCookies) {
         tinymce.util.Cookie.set(id+'_editorMode', arrTinyMceMode[id]);
      }
   };

   // labels must be an array with two string elements which are the labels for
   // the switch link
   $.fn.toggleModeTinyMCE = function(labels)
   {
      var id = this.attr('id');

      tinymce.EditorManager.execCommand('mceToggleEditor', false, id);
      if (arrTinyMceMode[id] == 'html') {
         $('#'+id+'_switch').text(labels[1]);
         arrTinyMceMode[id] = 'text';
      }
      else {
         $('#'+id+'_switch').text(labels[0]);
         arrTinyMceMode[id] = 'html';
      }
      if (boolTinyMceCookies) {
         tinymce.util.Cookie.set(id+'_editorMode', arrTinyMceMode[id]);
      }
   };
})(jQuery);