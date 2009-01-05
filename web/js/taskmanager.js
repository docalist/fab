/*
    Script permettant le suivi quasiment en temps réel de l'exécution
    d'une tâche.
    
    Daniel Ménard, 30/01/2008
*/
jQuery(document).ready(
function()
{
    // L'objet jQuery représentant la barre de progression
    var bar=null;
    
    // L'objet jQuery contenant le #updater éventuellement présent dans le source
    var updater=null;
    
    // Met à jour la page
    var update = function(data)
    {
        // Lors de l'initialisation, on est appellé sans paramètre
        if (data !== undefined)
        {
            // Remplace le span#updater existant par les données reçues
            updater=updater.replaceWith(data);
            
            // Si on n'a plus de span#updater, c'est que la tâche est terminée
            updater=jQuery('#updater');
            if (updater.length!==1) 
            {
                if (bar) bar.hide();
                return;
            } 
        }
        
        // Met à jour la barre de progression
        progress();
        
        // Attend un peu et lance une requête ajax pour recommencer
        window.setTimeout(ajax, 1000);
        
    };
    
    // Lance une nouvelle requête ajax pour mettre à jour la page
    var ajax = function()
    {
        var url=updater.attr('url');
        if (url=='') return; // aucune url indiquée, c'est une erreur
        
        xhr=jQuery.ajax
        (
            {
                type: 'GET',
                url: url,
                success: update
            }
        );
    };
    
    // Met à jour la barre de progression, l'affiche ou la masque selon les cas
    var progress = function()
    {
        var step=parseInt(updater.attr('step'));
        var max=parseInt(updater.attr('max'));
        
        // Pas d'attribut max, max vide ou max égal à zéro : cache la barre
        if (max == 0)
        {
            if (bar !== null) bar.hide();
            return;
        }
        
        // Crée la barre de progression si ce n'est pas encore fait 
        if (bar === null)
        {
            bar=jQuery('<div id="progressbar"><div></div><span>100%</span></div>');            
            bar.insertAfter(updater);
        }

        
        var percent=Math.floor(100 * step/max);
        bar.find('span').text(step + '/' + max + ' (' + percent + ' %)');
//        bar.find('span').text(percent + ' %');
        bar.find('div').css('width', percent+'%')
        bar.show();
    };

    // Initialisation du bazar
    updater=jQuery('#updater');
    
    // Le source ne contient pas de span#updater : la tâche n'est pas en cours d'exécution, terminé
    if (updater.length !== 1) return;
    
    // Lance la mise à jour régulière de la page
    update();
}
);