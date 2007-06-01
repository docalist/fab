function diff_divs(id1, id2)
{
    var div1=document.getElementById(id1);
    var div2=document.getElementById(id2);
    
    var text1=div1.innerHTML;
    var text2=div2.innerHTML;

    var diffs = diff_main(text1, text2, false);
    diff_cleanup_semantic(diffs)

    var t1=new Array();
    var t2=new Array();
    
    for(var i=0; i<diffs.length; i++)
    {
        var type=diffs[i][0];
        var item=diffs[i][1];

        //item = item.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
        item = item.replace(/\n/g, "<br>");
/*        item = item.replace(/\n/g, "&para;<br>");
        item = item.replace(/\t/g, "&rarr;");
        item = item.replace(/ /g, "&#8231;"); // &#8228;*/
        switch(type)
        {
            case DIFF_EQUAL: 
                t1.push(item);
                t2.push(item);
                break;
            case DIFF_DELETE: 
                t1.push('<span class="delete">' + item + '</span>');
                break;
            case DIFF_INSERT: 
                t2.push('<span class="insert">' + item + '</span>');
                break;
        }
    }
    
    div1.innerHTML=t1.join('');
    div2.innerHTML=t2.join('');
}
