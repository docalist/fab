// fonction appellée par rpc quand on reçoit les données envoyées par thesolookup
function ThesoLookup(popup)
{
    jQuery('li', popup).each(function(){
        jQuery(this)
        .click(function(event){
            jQuery.AutoCompleteHandler.set(jQuery(this).attr('term'));
        })
        .attr('title', 'Utiliser "' + jQuery(this).attr('term') + '"');
    });

    jQuery('a', popup).each(function(){
        jQuery(this)
        .click(function(event){
            var term=jQuery(this).text();
            term=term.replace(/[\[\]]/g, '');
            term = '[' + term + ']';
            
            popup.load('ThesoLookup?Fre='+escape(term), null, jQuery.AutoCompleteHandler.gotResult);
            event.stopPropagation();
            event.preventDefault();
            jQuery.AutoCompleteHandler.target.focus();
        })
        .attr('title', 'Afficher "' + jQuery(this).text() + '"')
        .attr('href','#');
    });
}