/**
    debug.js
    
    Fonctions javascript utilisees lorsque l'application est en mode debug
*/

function debugToggle(id, flag)
{
    if ( elt = document.getElementById(id) )
    {
        if (flag == undefined)
            elt.style.display = elt.style.display=='none' ? 'block' : 'none';
        else
            elt.style.display = flag ? 'block' : 'none';
    }
    return false;
}