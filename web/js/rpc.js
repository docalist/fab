/*

09/02/06
- d�tecte si on est appell� � partir d'autre chose qu'un 
�v�nement 'keydown' et g�n�re une alerte (une fois) si c'est le cas

08/02/06
- r�-�criture pour rendre le code compatible avec le packager

07/02/06
- r�-�criture compl�te sous forme d'objet
- ajout de commentaires
- nettoyage du code

06/02/06
- version initiale

*/
jQuery.AutoCompleteHandler =
{
    // Block div utilis� comme popup
    popup: null,

    // El�ment de formulaire � l'origine du dernier appel
    target:null,

    // Index de la suggestion actuellement s�lectionn�e (-1=aucune)
    current:-1,

    // la requ�te XmlHttpRequest en cours (null = aucune)
    xhr: null,
    xhrValue: null,
    
    // Le handle du timer de mise � jour en cours (null=aucun)
    timer: null,
    lastKeyTime:0,
    
    keepFocus: false,
    
    initialize: function(url, settings)
    {
        var popupId='autocompletePopup';
        
        // Cr�e le popup s'il n'existe pas d�j�
        if(jQuery.AutoCompleteHandler.popup==null)
        {
            jQuery('body', document).append
            (
                '<div id="'+popupId+'" style="display: none;position: absolute; top: 0; left: 0; z-index: 30001;"></div>'
                /*style=" display: none;"*/
            );
            jQuery.AutoCompleteHandler.popup=
                jQuery('#'+popupId)
                .mouseover(function(){
                    jQuery.AutoCompleteHandler.keepFocus=true;
                })
                .mouseout(function(){
                    jQuery.AutoCompleteHandler.keepFocus=false;
                });
                        
            if (jQuery.AutoCompleteHandler.popup.length===0)
            {
//                console.error('Impossible de cr�er le popup');
                return;
            }
        }
        
        // R�cup�re les param�tres et application les valeurs par d�faut
        defaultSettings=
        {
            url: url,
            delay: 500,
            asValue: false,
            asExpression: false
        };
        
        if (settings)
            settings=jQuery.extend(defaultSettings, settings);
        else
            settings=defaultSettings;
                            
	    // Initialise les contr�les
        return this.each(function(){
/*
            if (this.tagName != 'INPUT' && this.getAttribute('type') != 'text' )
            {
                console.error('impossible de faire du autocomplete sur un tag '+this.tagName);
                return;
            }
*/            
            this.ac=settings;
            this.ac.cache=new Array();

            jQuery(this)
            
                // D�sactive le autocomplete du navigateur
                .attr('autocomplete', 'off')
                
                // M�morise la target en cours lorsque le champ obtient le focus
                .focus(function(){
                    jQuery.AutoCompleteHandler.target=this;
//	                jQuery(this).attr('autocomplete', 'off');
                })
                
                // Met � null la target en cours et cache le popup quand le champ perd le focus

                .blur (function(){
//	                jQuery(this).attr('autocomplete', 'on');
                    if (jQuery.AutoCompleteHandler.keepFocus) return;
                    jQuery.AutoCompleteHandler.hide();
                    jQuery.AutoCompleteHandler.target=null;
                })

                // Point d'entr�e principal : appell� chaque fois que l'utilisateur tape une touche
                .keydown(function(event){

/*
vitesse de frappe de l'utilisateur
                    if (0==jQuery.AutoCompleteHandler.lastKeyTime)
                        jQuery.AutoCompleteHandler.lastKeyTime=(new Date()).getTime();
                    else
                    {
                        time=(new Date()).getTime();
                        console.info('speed', time-jQuery.AutoCompleteHandler.lastKeyTime);
                        jQuery.AutoCompleteHandler.lastKeyTime=time;
                    }
*/
                    // Si on a d�j� une requ�te � lancer en attente, on l'annule
                    if (jQuery.AutoCompleteHandler.xhr)
                    {
                        jQuery.AutoCompleteHandler.xhr.abort();
                        jQuery.AutoCompleteHandler.xhr=null;
                    }
                    
                    // Si on a d�j� un timer update en cours, on l'annule
                    if (jQuery.AutoCompleteHandler.timer) 
                    {
                        window.clearTimeout(jQuery.AutoCompleteHandler.timer);
                        jQuery.AutoCompleteHandler.timer=null;
                    }
                    
                    var special  = event.shiftKey 
                                || event.ctrlKey 
                                || event.altKey 
                                || (event.metaKey ? event.metaKey : false); // meta g�n�re undefined sous ie
                    
                    var nav=
                    {
                        33:'first',     // Page Up
                        36:'first',     // Home
                        34:'last',      // Page Down
                        35:'last',      // End
                        40:'next',      // Key Down
                        38:'previous',  // Key Up
                        27:'none',      // Esc
                        13:'current',   // Entr�e
                         9:'current'    // Tab
                    }

                    if ( nav[event.keyCode] )
                    {

                        if (special) return;
                        if (! jQuery.AutoCompleteHandler.visible)  return;
                        if (event.keyCode == 9 && jQuery.AutoCompleteHandler.current == -1) return;

                        jQuery.AutoCompleteHandler.select(nav[event.keyCode]);
                        event.preventDefault(); 
                        return false;
                    }
                    jQuery.AutoCompleteHandler.timer=window.setTimeout(jQuery.AutoCompleteHandler.update, 250); // entre 500 et 750
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
    
    // Retourne la position gauche absolue (X) de l'�l�ment pass� en param�tre
    _calculateOffsetLeft : function($element)
    {
        return this._calculateOffset($element, 'offsetLeft')
    },


    // Retourne la position haute absolue (Y) de l'�l�ment pass� en param�tre
    _calculateOffsetTop : function($element)
    {
        return this._calculateOffset($element, 'offsetTop')
    },


    // Calcule pour l'�l�ment pass� en param�tre la valeur de la propri�t� indiqu�e
    // en additionnant la valeur de cette propri�t� pour tous les anc�tres de l'�l�ment
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

    getTextRange: function(value, selection)
    {
        // D�finit la liste des s�parateurs qu'on reconnait
        var sep = /\s*(?:,|;|\/)\s*|\s+(?:et|ou|sauf|and|or|not|near|adj)\s*/ig;
        
        // texte SEP te|xte SEP texte SEP texte
        //       ^          ^
        //
        
        // a priori, un seul article, on prends tout du d�but � la fin
        start=0;
        end=value.length;

        // Recherche les s�parateurs
        sep.lastIndex=0; // indispensable comme on utilise /g et qu'on ne va pas toujours jusqu'au dernier match (exec recommece toujours � partir de lastIndex)

        while ((match = sep.exec(value)) != null)
        {
            // Sep apr�s le curseur, position de fin=un car avant
            if (match.index>selection)
            {
                end=match.index;
                break;
            }
            // le s�parateur est avant le curseur. nouveau start=fin du s�parateur
            start=match.index+match[0].length;
        }
        
        t=
            {
                start: start,
                end: end,
                value: value.substring(start,end),
                insep: (start > selection)
            };
        return t;
    },
    
    // Injecte la valeur pass�e en param�tre dans le champ
    set : function(item)
    {
        var target=jQuery.AutoCompleteHandler.target;
        var value=target.value;

        selectionStart=jQuery.AutoCompleteHandler.getSelectionStart(target);
        selection=jQuery.AutoCompleteHandler.getTextRange(value,selectionStart);

        if (target.ac.asValue)
            item='['+item+']';
        else
            if (target.ac.asExpression)
                item='"'+item+'"';
                
        target.value=value.substr(0, selection.start) + item + value.substr(selection.end);

        jQuery.AutoCompleteHandler.hide();
        jQuery.AutoCompleteHandler.target.focus();
    },

    // Affiche le popup
    show: function()
    {
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

    // G�re la navigation au sein du popup
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
                    if (item.attr('onclick'))
                        item.trigger('click');
                    else
                        jQuery.AutoCompleteHandler.set(item.text());                    
                    jQuery.AutoCompleteHandler.hide();
                }
                break;
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
                if (current == -1) return;
//                    console.error('Appel incorrect de select : ', what);
        }

        if (jQuery.AutoCompleteHandler.current > -1)
            items.eq(jQuery.AutoCompleteHandler.current).removeClass('selected');
        jQuery.AutoCompleteHandler.current=current;
        items.eq(current).addClass('selected');
    },
    
    update: function()
    {
        // R�cup�re la valeur du champ
        var target=jQuery.AutoCompleteHandler.target;
        if (!target) return;
        var value=target.value;
                    
        // Si la valeur actuelle est identique � la valeur pr�c�dente, termin�
        if (target.lastValue && target.lastValue==value) return;
        
        // M�morise la derni�re valeur saisie
        target.lastValue=value;
    
        // Si le champ de saisie est vide, cache la boite de r�sultats
        if (value=='')
        {
            jQuery.AutoCompleteHandler.hide();
            return;
        }

        selectionStart=jQuery.AutoCompleteHandler.getSelectionStart(jQuery.AutoCompleteHandler.target);
        selection=jQuery.AutoCompleteHandler.getTextRange(value,selectionStart);

        if (selection.insep || selection.value==='')
        {
            jQuery.AutoCompleteHandler.hide();
            return;
        }

        // Teste si le cache contient d�j� les r�sultats pour cette valeur
        jQuery.AutoCompleteHandler.xhrValue=selection.value;
        if (target.ac.cache)
        {
            var data=target.ac.cache[selection.value];
            if (data !== undefined) // on l'a en cache
            {
                jQuery.AutoCompleteHandler.gotResult(data);
                return;
            }
        }

        // Cache la boite en attendant qu'on ait les r�sultats
        jQuery.AutoCompleteHandler.xhr=jQuery.ajax({
            type: 'GET',
            url: target.ac.url.replace('%s', escape(selection.value)),
            success: jQuery.AutoCompleteHandler.gotResult
        });
    },

    
    gotResult: function(data)
    {
        jQuery.AutoCompleteHandler.xhr=null;
        jQuery.AutoCompleteHandler.current=-1;
        popup=jQuery.AutoCompleteHandler.popup;
        
        target=jQuery.AutoCompleteHandler.target;
        
        if (target.ac.cache)
            target.ac.cache[jQuery.AutoCompleteHandler.xhrValue]=data;

        popup.attr("innerHTML", data);
        
        items=jQuery(popup).children(0).children();
        if (items.length==0)
        {
            if (jQuery.AutoCompleteHandler.visible)
                jQuery.AutoCompleteHandler.hide();
            return;
        }
        items
            .mouseover(function(){
                jQuery.AutoCompleteHandler.select(this);
            })
            .css('cursor','pointer')
            .each(function(item){ // permet � l'�l�ment d'utiliser this->set(x) dans son onclick
                this.set=jQuery.AutoCompleteHandler.set;
            })
            ;
        
        if (! jQuery.AutoCompleteHandler.visible)
            jQuery.AutoCompleteHandler.show();
    }
    
    
};

jQuery.fn.autocomplete = jQuery.AutoCompleteHandler.initialize;