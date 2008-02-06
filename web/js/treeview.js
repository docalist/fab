
jQuery(document).ready(function()
{
    var onclick=function(event)
    {
        event.preventDefault();        

        // Le lien sur lequel on a cliqué
        var a = jQuery(this);
        
        // Le parent (li) du lien
        var li=jQuery(this.parentNode);
        
        // Si le li contient déjà un ul, on permute sa visiblité
        var ul=jQuery('ul',li);
        if (ul.size())
        {
            if (ul.is(":hidden"))
            {
                li.addClass('open');
                ul.slideDown('normal');
            }
            else
            {
                li.removeClass('open');
                ul.slideUp('normal');
            }
        }
        // Sinon, on lance une requête ajax
        else
        {
            li.addClass('loading');
            jQuery.ajax({
                type: 'GET',
                url: this.href,
                success: function(data)
                {
                    li.removeClass('loading');
                    jQuery(data).find('a.toggle').click(onclick).end().appendTo(li).hide();
                    a.trigger('click');
                }
            });
        }            
    };
    jQuery('ul.treeview a.toggle').click(onclick);
    return false;
    
});