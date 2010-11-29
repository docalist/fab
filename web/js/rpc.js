/*
    rpc.js - librairie javascript utilisée pour l'autocomplete
*/
/* Indique à jsLint les variables globales utilisées dans ce script : */
/*global window,document,jQuery*/

jQuery.AutoCompleteHandler =
{
    // Block div utilisé comme popup
    popup: null,

    // Elément de formulaire à l'origine du dernier appel
    target: null,

    // Index de la suggestion actuellement sélectionnée (-1=aucune)
    current: -1,

    // la requête XmlHttpRequest en cours (null = aucune)
    xhr: null,
    xhrValue: null,
    
    // Le handle du timer de mise à jour en cours (null=aucun)
    timer: null,
//    lastKeyTime: 0,
    
    keepFocus: false,
    
    // Initialise l'autocomplete pour le(s) contrôle(s) jquery sélectionné(s)
    initialize: function (url, settings) {

        // Initialisation faites lors du tout premier appel
        if (jQuery.AutoCompleteHandler.popup === null) {
            jQuery.AutoCompleteHandler.popup = 
                // Crée le popup
                jQuery('<div id="autocompletePopup" />').
                
                // L'ajoute au document
                //appendTo('body').
                
                // Si la souris survole le popup, on force le controle associé à garder le focus
                mouseover(function () {
                    jQuery.AutoCompleteHandler.keepFocus = true;
                }).
                mouseout(function () {
                    jQuery.AutoCompleteHandler.keepFocus = false;
                });
                
            // Pour que le popup fasse exactement la même largeur que la textbox,
            // on a besoin de connaître la taille en pixels des bors gauche et droite
            // du popup. On le calcule une fois pour toute ici en faisant la
            // différence entre offsetWidth et clientWidth et on stocke le 
            // résultat dans un expando. Rem : le popup doit être visible pour
            // que offsetWidth et clientWidth retournent la bonne valeur 
            var popup = jQuery.AutoCompleteHandler.popup;
            popup.show();
            popup.get(0).rpcBorderWidth = popup.get(0).offsetWidth - popup.get(0).clientWidth;
            popup.hide();
            
            // Ajoute un gestionnaire keydown au document plutôt qu'aux contrôles, comme ça, si
            // le contrôle perd le focus, les touches de direction restent fonctionnelles
            jQuery(document.documentElement).keydown(jQuery.AutoCompleteHandler.keydownHandler);
                
            // Crée un gestionnaire 'onsubmit' pour le formulaire pour remettre 
            // l'attribut autocomplete à 'on' pour tous les input et les textarea
            // nb : ne fonctionnera pas si on avait plusieurs form dans une même page
            jQuery(this.form).submit(function () {
                jQuery('input,textarea').attr('autocomplete', 'on');
            });
        }
        
        // Paramètres et valeurs par défaut du autocomplete
        var defaultSettings = {
            url: url,
            delay: 600,
            asValue: false,
            asExpression: false,
            height: 'auto',
            onload: null,
            
            // Nom de la classe qui sera ajoutée à tous les contrôles autocomplete
            className: 'autocomplete',
            
            // source html à ajouter devant les contrôles
            before: null,
            
            // Source html à ajouter après les contrôles
            after: null,
            
            title: null
        };
        
        if (settings) {
            settings = jQuery.extend(defaultSettings, settings);
        } else {
            settings = defaultSettings;
        }
              
        // si l'url contient &amp;, &gt; ou &lt; on les décode maintenant
        settings.url=settings.url.replace(/&amp;/g, '&')
                                 .replace(/&gt;/g, '>')
                                 .replace(/&lt;/g, '<');

        // Initialise l'autocomplete pour chacun des contrôles jquery sélectionnés
        return this.each(function () {
            this.ac = settings;
            this.ac.cache = [];

            jQuery(this).
                
                // Désactive l'autocomplete du navigateur
                attr('autocomplete', 'off').
                
                // Mémorise la target en cours lorsque le champ obtient le focus
                focus(function () {
                    jQuery.AutoCompleteHandler.target = this;
                }).
                
                // Met à null la target en cours et cache le popup quand le champ perd le focus
                blur(function () {
                    if (jQuery.AutoCompleteHandler.keepFocus) { 
                        return;
                    }
                    jQuery.AutoCompleteHandler.hide();
                    jQuery.AutoCompleteHandler.target = null;
                });
                
            if (settings.className) {
                jQuery(this).addClass(settings.className);
            }
            
            if (settings.before) {
                jQuery(this).before(settings.before);
            }
            
            if (settings.after) {
                jQuery(this).after(settings.after);
            }
            
            if (settings.title) {
                jQuery(this).attr('title', settings.title);
            }
        });
    },
    
    // Gère les touches de direction lorsque le popup est affiché
    keydownHandler: function (event) {
        if (! jQuery.AutoCompleteHandler.target) {
            return;
        }
        jQuery(jQuery.AutoCompleteHandler.target).focus();
        
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
        
        // Si on a déjà une requête en attente, on l'annule
        if (jQuery.AutoCompleteHandler.xhr) {
            jQuery.AutoCompleteHandler.xhr.acAborted = true;
            jQuery.AutoCompleteHandler.xhr.abort();
            jQuery.AutoCompleteHandler.xhr = null;
        }
        
        // Si on a déjà un timer update en cours, on le réinitialise
        if (jQuery.AutoCompleteHandler.timer) {
            window.clearTimeout(jQuery.AutoCompleteHandler.timer);
            jQuery.AutoCompleteHandler.timer = null;
        }
        
        var special  = 
            event.shiftKey  || 
            event.ctrlKey   || 
            event.altKey    || 
            (event.metaKey ? event.metaKey : false); // meta génère undefined sous ie
        
        var nav = {
            33: 'first',     // Page Up
            36: 'first',     // Home
            34: 'last',      // Page Down
            35: 'last',      // End
            40: 'next',      // Key Down
            38: 'previous',  // Key Up
            27: 'none',      // Esc
            13: 'current',   // Entrée
            9:  'current'    // Tab
        };

        if (nav[event.keyCode]) {
            if (special || ! jQuery.AutoCompleteHandler.visible) {
                return;
            }
            
            if ((event.keyCode === 9 || event.keyCode === 13) && jQuery.AutoCompleteHandler.current === -1) { 
                jQuery.AutoCompleteHandler.hide();
                return;
            }

            jQuery.AutoCompleteHandler.select(nav[event.keyCode]);
            event.preventDefault(); 
            return false;
        }
        
        // Si l'utilisateur est un certain temps sans rien faire, lance la requête ajax
        jQuery.AutoCompleteHandler.timer = 
            window.setTimeout(jQuery.AutoCompleteHandler.update, jQuery.AutoCompleteHandler.target.ac.delay); // entre 500 et 750
    },
                
    getSelectionStart : function (field) {
        if ('selectionStart' in field) {
            return field.selectionStart;
        } else if ('createTextRange' in field) {
            var selRange = document.selection.createRange();
            var selRange2 = selRange.duplicate();
            return 0 - selRange2.moveStart('character', -100000);
        }
    },

    getTextRange: function (value, selection) {
        // Définit la liste des séparateurs qu'on reconnait
        var sep = /\s*(?:,|;|\/)\s*|\s+(?:et|ou|sauf|and|or|not|near|adj)\s+/ig;
        
        // texte SEP te|xte SEP texte SEP texte
        //       ^          ^
        //
        
        // a priori, un seul article, on prends tout du début à la fin
        var start = 0;
        var end = value.length;

        // Recherche les séparateurs
        sep.lastIndex = 0; // indispensable comme on utilise /g et qu'on ne va pas toujours jusqu'au dernier match (exec recommence toujours à partir de lastIndex)

        var match;
        while ((match = sep.exec(value)) !== null) {
            // Sep après le curseur, position de fin=un car avant
            if (match.index > selection) {
                end = match.index;
                break;
            }
            // le séparateur est avant le curseur. nouveau start=fin du séparateur
            start = match.index + match[0].length;
        }
        
        var result = {
            start: start,
            end: end,
            value: value.substring(start, end),
            insep: (start > selection)
        };
        return result;
    },
    
    // Injecte la valeur passée en paramètre dans le champ
    set : function (item) {
        var target = jQuery.AutoCompleteHandler.target;
        var value = target.value;

        var selectionStart = jQuery.AutoCompleteHandler.getSelectionStart(target);
        var selection = jQuery.AutoCompleteHandler.getTextRange(value, selectionStart);

        // Ajoute des crochets ou des guillemets 
        if (target.ac.asValue) {
            item = '[' + item + ']';
        } else if (target.ac.asExpression) {
            // seulement si l'item contient des caractères spéciaux
            if (/[^_a-zA-Z0-9_ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþ]/.test(item))
            {
                item = '"' + item + '"';
            }
        }
                        
        target.value = value.substr(0, selection.start) + item + value.substr(selection.end);

        jQuery.AutoCompleteHandler.hide();
        jQuery.AutoCompleteHandler.target.focus();
    },

    // Affiche le popup
    show: function () {
        var target = jQuery(jQuery.AutoCompleteHandler.target);
        var offset = target.offset();
        var popup = jQuery.AutoCompleteHandler.popup;
        
        // positionne le popup en bas à gauche de la target avec la même largeur
        // que la target (popup.clientWidth = target.offsetWidth - popup.bordGauche - popup.bordDroite)
/*        
        popup.
            css('left', offset.left + 'px').
            css('top', offset.top + target.get(0).offsetHeight + 'px').
            width(target.get(0).offsetWidth - popup.get(0).rpcBorderWidth).
            fadeIn('normal');
*/        
        popup.
	        width(target.get(0).offsetWidth - popup.get(0).rpcBorderWidth).
	        insertAfter(target).
	        fadeIn('normal');
            
        jQuery.AutoCompleteHandler.visible = true;
        jQuery.AutoCompleteHandler.ensureVisible(
            jQuery.AutoCompleteHandler.popup.get(0), 
            document.documentElement);
    },

    // Cache le popup
    hide: function () {
        jQuery.AutoCompleteHandler.popup.fadeOut('normal');
        jQuery.AutoCompleteHandler.visible = false;

        // Si on a déjà une requête en attente, on l'annule
        if (jQuery.AutoCompleteHandler.xhr) {
            jQuery.AutoCompleteHandler.xhr.acAborted = true;
            jQuery.AutoCompleteHandler.xhr.abort();
            jQuery.AutoCompleteHandler.xhr = null;
        }
    },

    // Gère la navigation au sein du popup
    select : function (what, nomove) {
        var popup = jQuery.AutoCompleteHandler.popup;
        var current = jQuery.AutoCompleteHandler.current;
        var items = jQuery(popup).children(0).children();
        var item;
        
        switch (what) {
        case 'current':
            if (current > -1) {
                item = items.eq(current);
                item.click();
                jQuery.AutoCompleteHandler.hide();
            }
            break;
        case 'none':
            jQuery.AutoCompleteHandler.hide();
            return;
        case 'first':
            current = 0;
            break; 
        case 'last':
            current = items.length - 1;
            break; 
        case 'next':
            current = (current + 1) % items.length;
            break; 
        case 'previous':
            current = (current - 1 + items.length) % items.length;
            break; 
        default:
            // si what est un des items du popup, ok, sinon erreur
            current = items.index(what);
            if (current === -1) {
                return;
            }
        }

        if (jQuery.AutoCompleteHandler.current > -1) {
            items.eq(jQuery.AutoCompleteHandler.current).removeClass('selected');
        }
        jQuery.AutoCompleteHandler.current = current;
        item = items.eq(current).addClass('selected');
        
        if (item.length && ! nomove) {
            jQuery.AutoCompleteHandler.ensureVisible(item.get(0), popup.get(0));
        }
    },
    
    // S'assure qu'un item est visible en faisant scroller son conteneur
    // si nécessaire
    // Utilisé : 
    // 1. pour garantir que le popup sera visible dans la page, par exemple
    //    si le champ est tout en bas de l'écran (on fait scroller la fenêtre)
    // 2. pour garantir que la suggestion actuellement sélectionnée est visible
    //    au sein du popoup (on fait scroller le popup) 
    ensureVisible : function (item, container) {
        var offsetTop = parseInt(item.offsetTop, 10);
        var offsetBottom = offsetTop + item.offsetHeight;

        var clientHeight = container.clientHeight;

        var scrollTop = parseInt(container.scrollTop, 10);
        var scrollBottom = scrollTop + clientHeight;

        if (offsetTop < scrollTop) {
            container.scrollTop = offsetTop;
        } else if (offsetBottom > scrollBottom) {
            container.scrollTop = Math.min(offsetBottom - clientHeight, offsetTop);
            // max ci-dessus : si la hauteur de l'item est supérieure à la 
            // hauteur du containeur, affiche le début de l'item plutôt que la fin
        }
    },
        
    update: function () {
        // Récupère la valeur du champ
        var target = jQuery.AutoCompleteHandler.target;
        if (!target) {
            return;
        }
        var value = target.value;
                    
        // Mémorise la dernière valeur saisie
//        target.lastValue = value;
    
        // Si le champ de saisie est vide, cache la boite de résultats
        if (value === '') {
            jQuery.AutoCompleteHandler.hide();
            return;
        }

        var selectionStart = jQuery.AutoCompleteHandler.getSelectionStart(jQuery.AutoCompleteHandler.target);
        var selection = jQuery.AutoCompleteHandler.getTextRange(value, selectionStart);

        if (selection.insep || selection.value === '') {
            jQuery.AutoCompleteHandler.hide();
            return;
        }

        // Teste si le cache contient déjà les résultats pour cette valeur
        jQuery.AutoCompleteHandler.xhrValue = selection.value;
        if (target.ac.cache) {
            var data = target.ac.cache[selection.value];
            if (data !== undefined) { // on l'a en cache
                jQuery.AutoCompleteHandler.gotResult(data);
                return;
            }
        }

        jQuery.AutoCompleteHandler.showMessage('<p class="loading">Recherche de suggestions pour <strong>' + jQuery.AutoCompleteHandler.xhrValue + '</strong>...</p>');
        
        // Lance la requête ajax
        jQuery.AutoCompleteHandler.xhr = jQuery.ajax({
            type: 'GET',
            url: target.ac.url.replace(/%s/g, escape(selection.value)),
            success: jQuery.AutoCompleteHandler.gotResult,
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                if (! jQuery.AutoCompleteHandler.visible || this.acTarget !== jQuery.AutoCompleteHandler.target) {
                    return;
                }

                if (XMLHttpRequest.acAborted && XMLHttpRequest.acAborted === true) {
                    return;
                }
            
                var msg = textStatus ? textStatus : errorThrown;
                jQuery.AutoCompleteHandler.showMessage('<p class="error">Erreur : ' + msg + '</p>');
            },
            dataType: 'html',
            timeout: 30 * 1000, // timeout des requêtes en millisecondes
            
            acTarget: target
        });
    },

    showMessage: function (html) {
        var popup = jQuery.AutoCompleteHandler.popup;
        popup.attr('innerHTML', html);
        var height = jQuery.AutoCompleteHandler.target.ac.height;
        popup.height('auto');
        if (popup.height() > height) {
            popup.height(height);
        }
        if (! jQuery.AutoCompleteHandler.visible) {
            jQuery.AutoCompleteHandler.show();
        }
    },
    
    gotResult: function (data) {

        if (this.acTarget && this.acTarget !== jQuery.AutoCompleteHandler.target) {
            return;
        }
    
        jQuery.AutoCompleteHandler.xhr = null;
        jQuery.AutoCompleteHandler.current = -1;
        var popup = jQuery.AutoCompleteHandler.popup;
        
        var target = jQuery.AutoCompleteHandler.target;
        
        if (target.ac.cache) {
            target.ac.cache[jQuery.AutoCompleteHandler.xhrValue] = data;
        }
        
        popup.attr('innerHTML', data);

        var items = jQuery(popup).children(0).children();
        
        if (items.length === 0) {
            jQuery.AutoCompleteHandler.showMessage('<p class="nosuggestion">Aucune suggestion pour <strong>' + jQuery.AutoCompleteHandler.xhrValue + '</strong>...</p>');
            return;
        }

        var height = target.ac.height;
        popup.height('auto');
        if (popup.height() > height) {
            popup.height(height);
        }

        items.
            mouseover(function () {
                jQuery.AutoCompleteHandler.select(this, true);
                // lorsqu'on survole un item à la souris, on le sélectionne,
                // mais sans faire scroller le popup, sinon, lorsqu'un item
                // est très long, on ne peux pas naviguer à la fin (le scroll
                // fait que c'est toujours le début de l'élément qui est affiché)
            }).
            each(function (item) { // permet à l'élément d'utiliser this->set(x) dans son onclick
                this.set = jQuery.AutoCompleteHandler.set;
            });
        
        if (! jQuery.AutoCompleteHandler.visible) {
            jQuery.AutoCompleteHandler.show();
        }

        jQuery.AutoCompleteHandler.select(items[0]);
        
        if (target.ac.onload) {
            target.ac.onload(popup);
        }
    }
};

jQuery.fn.autocomplete = jQuery.AutoCompleteHandler.initialize;