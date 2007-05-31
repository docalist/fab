/*

09/02/06
- détecte si on est appellé à partir d'autre chose qu'un 
événement 'keydown' et génère une alerte (une fois) si c'est le cas

08/02/06
- ré-écriture pour rendre le code compatible avec le packager

07/02/06
- ré-écriture complète sous forme d'objet
- ajout de commentaires
- nettoyage du code

06/02/06
- version initiale

*/
jQuery.AutoCompleteHandler =
{
    // Block div utilisé comme popup
    popup: null,

    // Elément de formulaire à l'origine du dernier appel
    target:null,

    // Index de la suggestion actuellement sélectionnée (-1=aucune)
    current:-1,

    // la requête XmlHttpRequest en cours (null = aucune)
    xhr: null,
    
    // Le handle du timer de mise à jour en cours (null=aucun)
    timer: null,
    
    initialize: function(url, settings)
    {
        var popupId='autocompletePopup';
        
        // Crée le popup s'il n'existe pas déjà
        if(jQuery.AutoCompleteHandler.popup==null)
        {
            jQuery('body', document).append
            (
                '<div id="'+popupId+'" style="display: none;position: absolute; top: 0; left: 0; z-index: 30001;"></div>'
                /*style=" display: none;"*/
            );
            jQuery.AutoCompleteHandler.popup=jQuery('#'+popupId);
            if (jQuery.AutoCompleteHandler.popup.length===0)
                console.error('Impossible de créer le popup');
        }
        
        // Récupére les paramètres et application les valeurs par défaut
        settings=jQuery.extend(
            {
                url: url,
                delay: 500,
            }, settings);
            
	   // Initialise les contrôles
        return this.each(function(){
/*
            if (this.tagName != 'INPUT' && this.getAttribute('type') != 'text' )
            {
                console.error('impossible de faire du autocomplete sur un tag '+this.tagName);
                return;
            }
*/            
            this.autocomplete=settings;

            jQuery(this)
                // Désactive le autocomplete du navigateur
                .attr('autocomplete', 'off')
                
                // Mémorise la target en cours lorsque le champ obtient le focus
                .focus(function(){
                    jQuery.AutoCompleteHandler.target=this;
                })
                
                // Met à null la target en cours et cache le popup quand le champ perd le focus
/*                
                .blur (function(){
                    jQuery.AutoCompleteHandler.hide();
                    jQuery.AutoCompleteHandler.target=null;
                })
*/                
                // Point d'entrée principal : appellé chaque fois que l'utilisateur tape une touche
                .keypress(function(event){

                    // Si on a déjà une requête à lancer en attente, on l'annule
                    if (jQuery.AutoCompleteHandler.xhr)
                    {
                        console.info('Annulation requête xhr en cours');
                        jQuery.AutoCompleteHandler.xhr.abort();
                        jQuery.AutoCompleteHandler.xhr=null;
                    }
                    
                    // Si on a déjà un timer update en cours, on l'annule
                    if (jQuery.AutoCompleteHandler.timer) 
                    {
                        console.info('Annulation timer en cours');
                        window.clearTimeout(jQuery.AutoCompleteHandler.timer);
                        jQuery.AutoCompleteHandler.timer=null;
                    }
                    
                    var special  = event.shiftKey 
                                || event.ctrlKey 
                                || event.altKey 
                                || (event.metaKey ? event.metaKey : false); // meta génère undefined sous ie
                    
                    var nav=
                    {
                        33:'first',     // Page Up
                        36:'first',     // Home
                        34:'last',      // Page Down
                        35:'last',      // End
                        40:'next',      // Key Down
                        38:'previous',  // Key Up
                        27:'none',      // Esc
                        13:'current',   // Entrée
                         9:'current'    // Tab
                    }

                    if ( nav[event.keyCode] )
                    {
                        if (special) return;
                        if (! jQuery.AutoCompleteHandler.visible) return;
                        jQuery.AutoCompleteHandler.select(nav[event.keyCode]);
                            
                        if (event.keyCode != 9) 
                        {
                            event.preventDefault(); 
                            return false;
                        }
                    }
//                    console.info(event);
                    
                    if (event.charCode != 0) 
                    {
                        // Si la valeur actuelle est identique à la valeur précédente, terminé
                        if (this.lastValue && this.lastValue==this.value) return;
                        
                        console.info('Nouveau timer');
                        jQuery.AutoCompleteHandler.timer=window.setTimeout(jQuery.AutoCompleteHandler.update, 750); // entre 500 et 750
                    }
                })
                ;
        });
        
    },

    getSelectionStart : function(field)
    {
        if ('selectionStart' in field)
            return field.selectionStart;
        else if('createTextRange' in field)
        {
            var selRange = document.selection.createRange();
            var selRange2 = selRange.duplicate();
            return 0 - selRange2.moveStart('character', -100000);
            //result.end = result.start + range.text.length;
            /*var selRange = document.selection.createRange();
            var isCollapsed = selRange.compareEndPoints("StartToEnd", selRange) == 0;
            if (!isCollapsed)
                selRange.collapse(true);
            var bookmark = selRange.getBookmark();
            return bookmark.charCodeAt(2) - 2;*/
        }
    },
    
    // Retourne la position gauche absolue (X) de l'élément passé en paramètre
    _calculateOffsetLeft : function($element)
    {
        return this._calculateOffset($element, 'offsetLeft')
    },


    // Retourne la position haute absolue (Y) de l'élément passé en paramètre
    _calculateOffsetTop : function($element)
    {
        return this._calculateOffset($element, 'offsetTop')
    },


    // Calcule pour l'élément passé en paramètre la valeur de la propriété indiquée
    // en additionnant la valeur de cette propriété pour tous les ancêtres de l'élément
    _calculateOffset : function($element, $property)
    {
        var $offset=0;
        while ($element)
        {
            $offset += $element[$property]; 
            $element=$element.offsetParent;
        }
        return $offset;
    },


    // Injecte la valeur passée en paramètre dans le champ
    set : function(item)
    {
        console.info('valeur à injecter : ', item);

        var target=jQuery.AutoCompleteHandler.target;
        var value=target.value;
        console.log('Value=', value);

        var sep = /\s*(?:,|;|\/)\s*|\s+(?:et|ou|sauf|and|or|not|near|adj)\s*/ig;
        start=0;
        end=value.length;
        found=false;
        while ((match = sep.exec(value)) != null)
        {
            if (match.index>selection)
            {
                end=match.index-1;
                break;
            }
            start=match.index+match[0].length;
        }
        console.log('début :' , value.substr(0,start-1));
        console.log('item :' , item);
        console.log('fin :' , value.substr(end));
        value=value.substr(0,start-1)+item+value.substr(end);

		jQuery.AutoCompleteHandler.target.value=value;
        jQuery.AutoCompleteHandler.hide();
        jQuery.AutoCompleteHandler.target.focus();
    },

    // Affiche le popup
    show: function()
    {
        console.info('show');
        jQuery.AutoCompleteHandler.popup
            .css('left', jQuery.AutoCompleteHandler._calculateOffsetLeft(jQuery.AutoCompleteHandler.target)+"px")
            .css('top', jQuery.AutoCompleteHandler._calculateOffsetTop(jQuery.AutoCompleteHandler.target)+jQuery.AutoCompleteHandler.target.offsetHeight+2+"px")
            .width(jQuery(jQuery.AutoCompleteHandler.target).width())
//            .show();
//        jQuery(jQuery.AutoCompleteHandler.popup).css('display','block');
            .fadeIn('fast');
        jQuery.AutoCompleteHandler.visible=true;
    },

    // Cache le popup
    hide: function()
    {
        jQuery.AutoCompleteHandler.popup.hide();
//        jQuery(jQuery.AutoCompleteHandler.popup).css('display','none');
        jQuery.AutoCompleteHandler.visible=false;
    },

    // Gère la navigation au sein du popup
    select : function(what)
    {
        popup=jQuery.AutoCompleteHandler.popup;
        current=jQuery.AutoCompleteHandler.current;
        items=jQuery(popup).children(0).children();
        
        switch(what)
        {
            case 'current':
                if (current > -1)
                {
                    item=items.eq(current);
                    console.log(item);

                    if (item.attr('onclick'))
                    {
                        // déclenche l'événement onclick
                        item.trigger('click');
                    }
                    else
                        alert(item.text());                    
                }
            case 'none':
                jQuery.AutoCompleteHandler.hide();
                return;
            case 'first':
                current=0;
                break; 
            case 'last':
                current=items.length-1;
                break; 
            case 'next':
                current=(current+1) % items.length;
                break; 
            case 'previous':
                current=(current-1+items.length) % items.length;
                break; 
            default:
                // si what est un des items du popup, ok, sinon erreur
                current=items.index(what);
                if (current == -1)
                    console.error('Appel incorrect de select : ', what);
        }

        if (jQuery.AutoCompleteHandler.current > -1)
            items.eq(jQuery.AutoCompleteHandler.current).removeClass('selected');
        jQuery.AutoCompleteHandler.current=current;
        items.eq(current).addClass('selected');
    },
    
    update: function()
    {
        console.log('update');

        // Récupère la valeur du champ
        var target=jQuery.AutoCompleteHandler.target;
        var value=target.value;
                    
        // Si la valeur actuelle est identique à la valeur précédente, terminé
        if (target.lastValue && target.lastValue==value) return;
        
        // Mémorise la dernière valeur saisie
        target.lastValue=value;
    
        // Si le champ de saisie est vide, cache la boite de résultats
        if (value=='')
        {
            this.autocomplete.hide();
            return;
        }
        
        selection=jQuery.AutoCompleteHandler.getSelectionStart(jQuery.AutoCompleteHandler.target);
        
//*********
//        var sep = /^\s*(?:,|;|)\s*|\s+(?:et|ou|sauf|and|or|not|near|adj)\s+/i;
        var sep = /\s*(?:,|;|\/)\s*|\s+(?:et|ou|sauf|and|or|not|near|adj)\s*/ig;
        
        start=0;
        found=false;
        while ((match = sep.exec(value)) != null)
        {
            console.dir(match);
            if (match.index>selection)
            {
                value=value.substring(start, match.index);
                found=true;
                break;
            }
            start=match.index+match[0].length;
        }
        if (!found)
            value=jQuery.trim(value.substr(start));
        console.info('VALUE=', value);
        
        if(value.length==0) return;
        // Teste si le cache contient déjà les résultats pour cette valeur
/*
        if (target.autocomplete_cache)
        {
            var $cache=target.autocomplete_cache[value];
            if ($cache !== undefined) // on l'a en cache
            {
                jQuery.AutoCompleteHandler._show_results($cache);
                return;
            }
        }
*/
        // Cache la boite en attendant qu'on ait les résultats
//        jQuery.AutoCompleteHandler.hide();
//        url=target.autocomplete.url.replace('%s', escape(value));
//        jQuery.AutoCompleteHandler.popup.load(url, jQuery.AutoCompleteHandler.gotResult);
        jQuery.AutoCompleteHandler.xhr=jQuery.ajax({
            type: 'GET',
            url: target.autocomplete.url.replace('%s', escape(value)),
            success: jQuery.AutoCompleteHandler.gotResult
        });
    },

    gotResult: function(data)
    {
        jQuery.AutoCompleteHandler.xhr=null;
        jQuery.AutoCompleteHandler.current=-1;
        popup=jQuery.AutoCompleteHandler.popup;

        popup.attr("innerHTML", data);
        
        items=jQuery(popup).children(0).children();
        if (items.length==0)
        {
            if (jQuery.AutoCompleteHandler.visible)
                jQuery.AutoCompleteHandler.hide();
            return;
        }
        console.log(items, ', lenght=', items.length);
        items
            .mouseover(function(){
                jQuery.AutoCompleteHandler.select(this);
            })
            .css('cursor','pointer')
            .each(function(item){ // permet à l'élément d'utiliser this->set(x) dans son onclick
                this.set=jQuery.AutoCompleteHandler.set;
            })
            ;
        
        if (! jQuery.AutoCompleteHandler.visible)
            jQuery.AutoCompleteHandler.show();
    }
    
    
};

jQuery.fn.autocomplete = jQuery.AutoCompleteHandler.initialize;
