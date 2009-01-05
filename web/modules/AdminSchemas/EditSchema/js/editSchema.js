var jQuery;
var SchemaEditor =
{
    /**
     * La structure de base de données en cours d'édition
     */
    db: null,
    
    /**
     * Propriétés et valeurs par défaut des différents types d'objet manipulés 
     */
    defaults:
    {
        db_fields:              // La liste des champs de la base
        {
            name: 'Champ',      // Nom du champ
            type: 'text',       // Type du champ (juste à titre d'information, non utilisé pour l'instant)
            label: '',          // Libellé du champ
            description: '',    // Description
            defaultstopwords: true, // Utiliser les mots-vides de la base
            stopwords: ''      // Liste spécifique de mots-vides à appliquer à ce champ
        },
        
        db_indices:             // La liste des index de la base
        {
            name: 'Index',      // Nom de l'index
            label: '',          // Libellé de l'index
            description: '',    // Description de l'index
            fields: []          // Liste des champs qui alimentent cet index
        },
        db_indices_fields:      // La liste des champs d'un index
        {
            name: '',           // Nom du champ
            words: false,       // Indexer les mots
            phrases: false,     // Indexer les phrases
            values: false,      // Indexer les valeurs
            count: false,       // Compter le nombre de valeurs
            global: false,      // Prendre en compte cet index dans l'index 'tous champs'
            start: '',          // Position ou chaine indiquant le début du texte à indexer
            end: '',            // Position ou chain indquant la fin du texte à indexer
            weight: 1           // Poids des tokens ajoutés à cet index
        },
        
        db_lookuptables:        // La liste des tables de lookup
        {
            name: 'Table',      // Nom de la table de lookup
            label: '',          // Libellé de la table
            description: '',    // Description de la table
            fields: []          // Liste des champs qui alimentent cette table
        },
        db_lookuptables_fields: // La liste des champs d'une table de lookup
        {
            name: '',           // Nom du champ
            start: '',          // Position de début ou chaine délimitant le début de la valeur à ajouter à la table
            end: ''             // Longueur ou chaine délimitant la fin de la valeur à ajouter à la table
        },
                
        db_aliases:             // La liste des alias de la base
        {
            name: 'Alias',      // Nom de l'alias
            label: '',          // Libellé de l'alias
            description: '',    // Description de l'alias
            indices: []         // Liste des index qui composent cet alias
        },
        db_aliases_indices:     // La liste des index d'un alias
        {
            name: ''            // Nom de l'index
        },
        
        db_sortkeys:            // La liste des clés de tri de la base
        {
            name: 'Tri',        // Nom de la clé de tri
            label: '',          // Libellé du tri
            description: '',    // Description du tri
            type: 'string',     // Type de la clé de tri (string, number)
            fields: []          // Champs (étudiés dans l'ordre, s'arrête au premier non vide)
        },
        db_sortkeys_fields:     // La liste des champs d'une clé de tru
        {
            name: '',           // Nom du champ
            start: '',          // Position de début ou chaine délimitant le début de la valeur à ajouter à la clé de tri
            end: '',            // Longueur ou chaine délimitant la fin de la valeur à ajouter à la clé de tri
            length: ''          // Longueur totale de la partie de clé (tronquée ou paddée à cette taille)
        }
    },

    /**
     *  La vitesse souhaitée pour les "effets spéciaux"
     */ 
    fxSpeed: 'fast',
    
      
    /**
     * L'url et les paramètres pour sauvegarder la structure de la base
     */
    saveUrl: null,
    saveParams: null,
       

    /**
     * Charge la base de données dans l'éditeur
     * Les propriétés simples de la base sont chargées directement dans le champ
     * correspondant (par exemple la propriété db.label sera chargée dans le 
     * champ input#db_label)
     *
     * Pour les propriétés qui sont des tableaux (fields, indices...), le
     * select correspondant est recherché (par exemple select#db_indices pour
     * le tableau db.indices) et une option est ajoutée au select pour chacun
     * des éléments trouvés dans le tableau. Pour chaque option, un expando
     * (data) contenant l'élément de tableau est créé.
     *
     * La fonction select() appellée lorsque l'une des options du select est
     * sélectionnée se chargera d'initialiser l'interface pour chacune des 
     * propriétés de l'objet ajouté à l'option.
     * 
     * @param object object la structure de base de données à charger
     * @param string id le préfixe à appliquer aux propriétés trouvées dans
     * l'objet pour déterminer l'id du champ correspondant de formulaire
     */
    loadStructure: function ()
    {
        var id='#db_';
        var properties = {};
        
        // Balaye toutes les propriétés de la base, on va gèrer nous mêmes les
        // tableaux (initialisation des select) et on va construire la liste
        // des propriétés simples (properties) qu'on chargera ensuite en 
        // appellant loadObject(properties) 
        for (var prop in this.db)
        {
            // Si c'est un tableau, on initialise le select correspondant
            if (this.db[prop] instanceof Array)
            {
                // Recherche le select correspondant
                var select = jQuery('select' + id + prop);
                
                // Si on ne le trouve pas, on le signale 
                if (select.length === 0)
                {
                    continue;
                }

                // Initialise l'évènement onchange du select
                select.change(SchemaEditor.select);
                
                // Touche suppr sur un select = supprimer l'option (shift+suppr = pas de confirmation)
                select.keydown(
                    function (e)
                    {
                        if (e.keyCode === 46)
                        { 
                            SchemaEditor.deleteOption(this, e.shiftKey === true);
                        }
                    }
                );

                // Crée un expando 'current' pour le select
                select.get(0).current = -1;
                
                var array = this.db[prop];
                
                // Pour chaque élément du tableau, on ajoute une option au select
                for (var i in array)
                {
                    // Crée une nouvelle option
                    var option = document.createElement('option');
                    option.text = array[i].name;
                    
                    // Stocke l'élément en cours du tableau comme expando 'data' de l'option
                    option.data = array[i];

                    // Ajoute l'option dans le select
                    try // source : http://www.w3schools.com/htmldom/met_select_add.asp
                    {
                        select.get(0).add(option, null); // standards compliant
                    }
                    catch (exception)
                    {
                        select.get(0).add(option); // IE only
                    } 
                }
                
                // Sélectionne le premier élément du select ou masque le right panel si le tableau est vide
                select.change();
            }

            // Sinon (valeur scalaire ou objet) on charge directement la propriété
            else
            {
                properties[prop] = this.db[prop];
            }
        }
        this.loadObject('#db', properties);
    },

    
    /**
     * Fonction appellée lorsque l'utilisateur change l'élément en cours dans un 
     * select. Affiche les propriétés de l'option sélectionnée.
     *
     * @param {event} e l'évènement qui a déclenché l'appel de la fonction
     */
    select: function (e)
    {
        SchemaEditor.saveSelect(this);
        
        // Si aucun élément n'est sélectionné et qu'il y a des options, sélectionne automatiquement la première
        if (this.selectedIndex === -1 && this.options.length > 0)
        {
            this.selectedIndex = 0;
        }
        
        // Toujours aucun élément : masque le panel de droite et affiche le message 'liste vide'
        if (this.selectedIndex === -1)
        {
            jQuery('#' + this.id + '_rightpanel').hide();
            jQuery('#' + this.id + '_empty').show();
            return;
        }

        // Sauvegarde l'index de l'élément en cours
        this.current = this.selectedIndex;
                
        // On s'assure que le panneau de droite est visible et que le message 'liste vide' est masqué
        jQuery('#' + this.id + '_rightpanel').show();
        jQuery('#' + this.id + '_empty').hide();
        
        // Affiche les propriétés de l'option sélectionnée
        SchemaEditor.loadObject('#' + this.id, this.options[this.selectedIndex].data);
    },


    /**
     * Charge dans l'éditeur une valeur scalaire, les propriétés d'un objet ou
     * les éléments d'un tableau.
     *
     * Les valeurs scalaires sont chargées en tenant compte du type du champ.
     * Pour un champ texte, la valeur est simplement injectée, pour une case à 
     * cocher, la valeur est évaluée et la case est cochée si celle-ci s'évalue 
     * à true.
     * Pour les objets, on récursive sur chacune des propriétés.
     * Pour les tableaux, on les charge dans le tableau html correspondant, en
     * ajoutant ou en supprimant des lignes à la table.
     *
     * @param {string} id l'ID du contrôle dans lequel il faut charger
     * la valeur (vous devez indiquer le '#' de début)
     *
     * @param {mixed} what la valeur à charger
     *
     * @param {mixed} context (optionnel) un contexte qui est passé à jQuery
     * pour rechercher l'élément dont l'ID est indiqué (jQuery(id,context)
     *
     * @return {void}
     */    
    loadObject: function (id, what, context)
    {
        if (id.charAt(0) !== '#')
        {
            return;
        }
            
        for (var prop in what)
        {
            var propid = id + '_' + prop;
            var data = what[prop];
            
            // La propriété est un tableau : on va le charger dans la table correspondante
            if (data instanceof Array)
            {
                var table = jQuery(propid);
    
                // Si la table n'existe pas, on le signale
                if (table.length === 0)
                {
                    continue;
                }
                
                // Si c'est le premier appel, sauve la TR qui sert de modèle aux autres
                if (typeof(table.get(0).tr) === 'undefined')
                {
                    table.get(0).tr = jQuery('tr:nth-child(2)', table).remove();
                }
                
                // Le tableau contient des éléments : on affiche la table
                if (data.length)
                {    
                    jQuery('tr:not(:first)', table).remove();
                    for (var i in data)
                    {
                        var tr = table.get(0).tr.clone(true);
                        SchemaEditor.loadObject(propid, data[i], tr);
                        table.append(tr);
                    }
                    
                    jQuery(propid + '_empty').hide();
                    jQuery(propid).show();

                    // Beurk ! le tr.clone(true) ne duplique pas les évènements pour les petits-enfants,
                    // donc le onclick du bouton est perdu. On le répête... (jquery 1.2.1)
                    jQuery('.deletetr', table).click(
                        function ()
                        {
                            SchemaEditor.deleteTr(jQuery(this).parents('tr:first').get(0));
                        }
                    );
                    
                }
                
                // Tableau vide : masque la table, affiche la mention 'aucun item'
                else
                {
                    jQuery(propid).hide();
                    jQuery(propid + '_empty').show();
                }
            }
            
            // La propriété est un objet : récursive pour charger chacune des propriétés
            else if (data instanceof Object)
            {
                this.loadObject(propid, data, context);
            }
            
            // La propriété est une valeur scalaire
            else
            {
                // Recherche le champ dans le contexte indiqué
                var control = jQuery(propid, context);
                
                // On signale si on ne le trouve pas
                if (control.length === 0)
                {
                    continue;
                }
                    
                if (control.is(':checkbox'))
                {
                    control.attr('checked', data ? true : false);
                }
                else
                {
                    if (data===null) data='';
                    control.val(data);
                }
            }
        }
    },


    /**
     * Initialise les propriétés d'un objet à partir de la valeur des champs correspondants d'un formulaire.
     * 
     * Pour chaque propriété de l'objet, la fonction va rechercher un contrôle ayant
     * pour identifiant l'id indiqué suivi d'un underscrore et du nom de la propriété
     * et va initialiser la propriété avec la valeur de ce contrôle.
     * 
     * @param {string} id le début de l'identifiant des contrôles à rechercher
     * @param {mixed} what l'objet à sauvegarder
     * @param {mixed} context le contexte éventuel dans lequel il faut rechercher l'id
     */
    saveObject: function (id, what, context)
    {
        for (var prop in what)
        {
            var propid = id + '_' + prop;
            var data = what[prop];
            
            // La propriété est un tableau : on va le charger dans la table correspondante
            if (data instanceof Array)
            {
                var table = jQuery(propid);
    
                // Si la table n'existe pas, on le signale
                if (table.length === 0)
                {
                    continue;
                }
                
                // Appelle saveObject pour chacune des lignes du tableau
                var length = jQuery('tr:not(:first)', table).each(
                    function (i)
                    {
                        SchemaEditor.saveObject(propid, data[i], this);
                    }
                ).length;

                // Supprime du tableau tous les éléments qui ne sont pas dans la table
                what[prop] = data.slice(0, length);
            }
            
            // La propriété est un objet : récursive pour charger chacune des propriétés
            else if (data instanceof Object)
            {
                this.saveObject(propid, data[prop], context);
            }
            
            // La propriété est une valeur scalaire
            else
            {
                // Recherche le champ dans le contexte indiqué
                var control = jQuery(propid, context);
                
                // On signale si on ne le trouve pas
                if (control.length === 0)
                {
                    continue;
                }
                
                // Met à jour la propriété en fonction de la valeur du contrôle                    
                if (control.is(':checkbox'))
                {
                    what[prop] = (control.attr('checked') === true);
                }
                else
                {
                    what[prop] = control.val();
                }    
            }        
        }        
    },
    

    
    saveSelect: function (select)
    {
        if (select.current === -1)
        {
            return;
        }
        
        this.saveObject('#' + select.id, select.options[select.current].data);
        
        var name = select.options[select.current].data.name;
        if (name !== select.options[select.current].text)
        {
            select.options[select.current].text = name;
        }
    },

    
    /**
     * Initialise l'éditeur
     * 
     * Cette méthode est appellée par jQuery.ready() une fois que la structure de la base a été reçue.
     * On initialise les formulaires à partir des données présentes dans la structure et on sélectionne
     * le premier champ de la base comme champ en cours.
     * 
     * @param {object} json un tableau contenant la structure de la base
     */
    init: function (db)
    {
        this.db=db;
        
        this.loadStructure();

        // Initialise la boite de dialogue utilisée pour afficher les erreurs
        //jQuery('#errors').jqm({modal: true, overlay: 20}).jqDrag('.jqDrag').jqResize('.jqResize');
        
        // Boutons monter/descendre en-dessous des select
        jQuery('.moveup').click(
            function ()
            {
                SchemaEditor.moveOption(jQuery('select', jQuery(this).parent().parent()).get(0), -1);
            }
        );
        jQuery('.movedown').click(
            function ()
            {
                SchemaEditor.moveOption(jQuery('select', jQuery(this).parent().parent()).get(0), 1);
            }
        );
        jQuery('.addoption').click(
            function ()
            {
                SchemaEditor.addOption(jQuery('select', jQuery(this).parent().parent()).get(0));
            }
        );
        jQuery('.deleteoption').click(
            function ()
            {
                SchemaEditor.deleteOption(jQuery('select', jQuery(this).parent().parent()).get(0));
            }
        );
        jQuery('.addtr').click(
            function ()
            {
                SchemaEditor.addTr(jQuery('table', jQuery(this).parent().parent()).get(0));
            }
        );
        jQuery('.deletetr').click(
            function ()
            {
                SchemaEditor.deleteTr(jQuery(this).parents('tr:first').get(0));
            }
        );
        

    },

    /**
     * Ajoute une option dans un select 
     * 
     */
    addOption: function (select)
    {
        // Initialise les propriétés de l'option avec leurs valeurs par défaut
        if (typeof SchemaEditor.defaults[select.id] === 'undefined')
        {
            return;
        }
            
        var data = SchemaEditor.clone(SchemaEditor.defaults[select.id]);
        
        // Détermine un nom qui ne soit pas déjà utilisé pour l'option
        for (var i = 1, name = data.name + '1'; ; i++, name = data.name + i)
        {
            if (!jQuery('option[value=' + name + ']', select).length) 
            {
                break;
            }
        }
        data.name = name;

        // Crée une option, ajoute field comme expando de cette option et ajoute l'option au select
        var option = document.createElement('option');
        option.text = data.name;
        option.data = data;
        if (select.options.length) jQuery(option).hide();

        try // source : http://www.w3schools.com/htmldom/met_select_add.asp
        {
            select.add(option, null); // standards compliant
        }
        catch (exception)
        {
            select.add(option); // IE only
        }        

        // Sélectionne et affiche la nouvelle option
        option.selected = true;
        jQuery(select).change();
/*
le fait de faire un fadeIn sur l'option (pour que l'utilisateur voit l'option
ajoutée) pose problème lorsque la liste est vide et que c'est la première option 
ajoutée (le select est réduit à une ligne, le libellé de l'option n'apparaît pas
dans le select) donc on ne le fait que s'il existe déjà des options dans le select
*/        
        jQuery(option).fadeIn(
            SchemaEditor.fxSpeed, 
            function ()
            {
                select.scrollTop = select.scrollHeight;
            }
        );

        jQuery('#' + select.id + '_rightpanel input:first').select().focus();
    },


    /**
     * Supprime une option d'un select
     */
    deleteOption: function (select, noconfirm)
    {
        var index = select.selectedIndex;

        // Sanity check
        if (index === -1)
        {
            return alert('Aucun élément sélectionné');
        }
            
        // Demande confirmation
        if (noconfirm !== true)
        {
            if (!confirm('Supprimer ' + select.options[index].text + ' ?')) 
            {
                return;
            }
        }

        // Supprime l'option en cours
        var scrollTop = select.scrollTop;
        jQuery(select.options[index]).fadeOut(
            SchemaEditor.fxSpeed,
            function ()
            {
                select.remove(index);
                select.current = -1; // empèche saveField de sauvegarder un champ qui n'existe plus
                
                // Si le champ supprimé n'était pas le dernier, on garde le même index
                // Sinon, on remonte d'un cran
                select.selectedIndex = index < select.options.length ? index : index - 1;
                jQuery(select).change();
                select.scrollTop = scrollTop;
            }
        );
    },

    
    /**
     * Monte ou descend un élément dans une liste
     *
     * @param select select la liste sur laquelle il faut intervenir
     * @param int delta le nombre de positions (positif=le champ descend, négatif=le champ monte)
     */
    moveOption: function (select, delta)
    {
        // Récupère l'option en cours, exit si aucune
        var index = select.selectedIndex;
        if (index === -1) 
        {
            return;
        }

        // Calcule le nouvel index, exit si hors limites
        var newIndex = index + delta;
        if (newIndex < 0 || newIndex >= select.options.length) 
        {
            return;
        }
        
        // Supprime l'option et la réinsère à la nouvelle position
        var option = select.options[index];
        select.remove(index);
        select.add(option, select.options[newIndex]);

        // Met à jour current pour qu'il reste synchronisé 
        select.current = select.selectedIndex;
    },
    

    /**
     * Ajoute une ligne dans une table
     */
    addTr: function (table)
    {
        // l'id de la table est de la forme db_indices_fields
        // on va découper cet id pour obtenir :
        // - l'id du select (id) auquel cette table est rattachée (db_indices)
        // - le nom de la propriété (prop) que cette table représente (fields)
        var i = table.id.lastIndexOf('_');
        var id = table.id.substring(0, i);
        var prop = table.id.substring(i + 1);
        
        // On récupère ensuite les données associées à l'option en cours au sein
        // du select (data)
        var select = jQuery('#' + id).get(0);
        var data = select.options[select.current].data;

        // On sauvegarde toutes les zones éventuellement modifiées
        SchemaEditor.saveObject('#' + id, data);
        
        //  et on ajoute un élément au tableau data[prop]
        if (typeof SchemaEditor.defaults[table.id] === 'undefined')
        {
            return;
        }
        
        data[prop].push(SchemaEditor.clone(SchemaEditor.defaults[table.id]));
        
        // Ensuite on demande un réaffichage de l'option
        SchemaEditor.loadObject('#' + id, data);
        jQuery('tr:last', table).hide().fadeIn(SchemaEditor.fxSpeed);
        jQuery('tr:last input:first', table).focus();
    },
    
    /**
     * Ajoute une ligne dans une table
     */
    deleteTr: function (tr)
    {
        // Récupère la table qui contient le tr à supprimer
        var table = jQuery(tr).parents('table:first').get(0);
        if (! table) 
        {
            return; 
            // bizarre... lié au fait qu'on est obligé de réappliquer le click() 
            // sur les .deletetr, du coup on est appellé deux fois, 
            // mais la 2nde fois, le tr n'a plus de parent
        }
        
        // Récupère l'id du select auquel est rattachée cette table
        var id = table.id.substring(0, table.id.lastIndexOf('_'));
        
        // Supprime la tr
        jQuery(tr).remove();

        // Sauvegarde puis réaffiche tout
        jQuery('#' + id).change();
        
        // impossible de faire un fadeOut : on veut animer l'élément qu'on
        // supprime et ça fait planter jQuery
    },
    
    /**
     * Change le nom du champ en cours
     */
    updateFieldName: function ()
    {
//        this.option.field.name = /*this.option.text=*/jQuery('#field_name').val();
    },
    
    setFieldClass: function (field, domOption)
    {
        var option = jQuery(domOption);
        option.removeClass();
        if (field.index.length)
        {
            option.addClass('index');
        }
        
        if (field.lookuptables.length)
        {
            option.addClass('entries');
        }
    },
    
    saveTo : function (url, params)
    {
        this.saveUrl = url;
        this.saveParams = params;
    },
     
    
    save : function ()
    {

        jQuery('select').change();
        var id='#db_';
        
        //
        for (var prop in this.db)
        {
            // Si c'est un tableau, on initialise le select correspondant
            if (this.db[prop] instanceof Array)
            {
                // Recherche le select correspondant
                var select = jQuery('select' + id + prop);
                
                // Si on ne le trouve pas, on le signale 
                if (select.length === 0)
                {
                    continue;
                }
                select=select.get(0);
                
                this.db[prop]=[];
                                
                // Pour chaque option du select, on ajoute un élément au tableau
                for (var i=0; i<select.options.length; i++)
                {
                    this.db[prop][i]=select.options[i].data;
                }
            }

            // Sinon (valeur scalaire ou objet) on charge directement la propriété
            else
            {
                var propid = id + prop;
                
                // Recherche le champ dans le contexte indiqué
                var control = jQuery(propid);
                
                // On signale si on ne le trouve pas
                if (control.length === 0)
                {
                    continue;
                }
                
                if (control.is(':checkbox'))
                {
                    this.db[prop] = (control.attr('checked') === true);
                }
                else
                {
                    this.db[prop] = control.val();
                }    
                
            }
        }

        // Masque la zone d'erreurs si elle est affichée
        jQuery('#errors').hide();

        jQuery.ajax(
            {
                type: 'POST',
                url: this.saveUrl,
                data: jQuery.extend({schema: jQuery.toJSON(this.db)}, this.saveParams), /* clone(); */
                dataType: 'json',
                success: function (data)
                {
                    if (typeof(data) === 'string')
                    {
                        if (confirm("Aucune erreur détectée, la structure de la base a été enregistrée dans le fichier " + SchemaEditor.saveParams.file))
                        {
                            window.location = data;
                        }
                    }
                    else
                    {
                        var errors = '';
                        jQuery(data).each(function (i)
                        {
                            errors += '<li>' + this + '</li>';
                        });
                        jQuery('#errors ul').html(errors);
                        jQuery('#errors').show();
                    }
                }
            }
        );
        
    },
    
    load: function (data)
    {
        switch (typeof(data))
        {
        // Une chaine : on considère que c'est une url à partir de laquelle on va charger la structure 
        case 'string':
            jQuery.getJSON(
                data, 
                function (json) 
                {
                    SchemaEditor.init(json); 
                    jQuery('#SchemaEditor').fadeIn(SchemaEditor.fxSpeed);
                }
            );
            break;
            
        // Un objet : on nous a passé directement la structure en paramètre
        case 'object':
            SchemaEditor.init(data);
            jQuery('#SchemaEditor').fadeIn(SchemaEditor.fxSpeed);
            break;
        default:
            alert('Impossible de déterminer le type de données passé à SchemaEditor.load()');
        }
    },
    
    /**
     * Retourne un clone de l'objet passé en paramètre.
     *
     * Si le paramètre est un scalaire, il est retourné tel quel.
     * S'il s'agit d'un tableau, un nouveau tableau contenant des clones des
     * éléments du tableau d'origine est retourné.
     * S'il s'agit d'un objet, un nouvel objet contenant des clones des 
     * propriétés de l'objet initial est retourné.
     *
     * @param mixed what la valeur, le tableau ou l'objet à cloner
     * @return mixed
     */
    clone: function (what)
    {
        var result;
        
        if (what instanceof Array)
        {
            result = [];
        }
        else if (what instanceof Object)
        {
            result = {};
        }
        else
        {
            return what;
        }
        
        for (var i in what)
        {
            result[i] = this.clone(what[i]);
        }
    
        return result;
    }
};