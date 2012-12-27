var smf_formSubmitted = false;

// Define document.getElementById for Internet Explorer 4.
if (typeof(document.getElementById) == "undefined")
    document.getElementById = function (id)
    {
        // Just return the corresponding index of all.
        return document.all[id];
    }
// Define XMLHttpRequest for IE 5 and above. (don't bother for IE 4 :/.... works in Opera 7.6 and Safari 1.2!)
else if (!window.XMLHttpRequest && window.ActiveXObject)
    window.XMLHttpRequest = function ()
    {
        return new ActiveXObject(navigator.userAgent.indexOf("MSIE 5") != -1 ? "Microsoft.XMLHTTP" : "MSXML2.XMLHTTP");
    };

// Some older versions of Mozilla don't have this, for some reason.
if (typeof(document.forms) == "undefined")
    document.forms = document.getElementsByTagName("form");

// Remember the current position.
function storeCaret(text)
{
    // Only bother if it will be useful.
    if (typeof(text.createTextRange) != "undefined")
        text.caretPos = document.selection.createRange().duplicate();
}

// Replaces the currently selected text with the passed text.
function replaceText(text, textarea)
{
    // Attempt to create a text range (IE).
    if (typeof(textarea.caretPos) != "undefined" && textarea.createTextRange)
    {
        var caretPos = textarea.caretPos;

        caretPos.text = caretPos.text.charAt(caretPos.text.length - 1) == ' ' ? text + ' ' : text;
        caretPos.select();
    }
    // Mozilla text range replace.
    else if (typeof(textarea.selectionStart) != "undefined")
    {
        var begin = textarea.value.substr(0, textarea.selectionStart);
        var end = textarea.value.substr(textarea.selectionEnd);
        var scrollPos = textarea.scrollTop;

        textarea.value = begin + text + end;

        if (textarea.setSelectionRange)
        {
            textarea.focus();
            textarea.setSelectionRange(begin.length + text.length, begin.length + text.length);
        }
        textarea.scrollTop = scrollPos;
    }
    // Just put it on the end.
    else
    {
        textarea.value += text;
        textarea.focus(textarea.value.length - 1);
    }
}

// Surrounds the selected text with text1 and text2.
function surroundText(text1, text2, textarea)
{
    // Can a text range be created?
    if (typeof(textarea.caretPos) != "undefined" && textarea.createTextRange)
    {
        var caretPos = textarea.caretPos, temp_length = caretPos.text.length;

        caretPos.text = caretPos.text.charAt(caretPos.text.length - 1) == ' ' ? text1 + caretPos.text + text2 + ' ' : text1 + caretPos.text + text2;

        if (temp_length == 0)
        {
            caretPos.moveStart("character", -text2.length);
            caretPos.moveEnd("character", -text2.length);
            caretPos.select();
        }
        else
            textarea.focus(caretPos);
    }
    // Mozilla text range wrap.
    else if (typeof(textarea.selectionStart) != "undefined")
    {
        var begin = textarea.value.substr(0, textarea.selectionStart);
        var selection = textarea.value.substr(textarea.selectionStart, textarea.selectionEnd - textarea.selectionStart);
        var end = textarea.value.substr(textarea.selectionEnd);
        var newCursorPos = textarea.selectionStart;
        var scrollPos = textarea.scrollTop;

        textarea.value = begin + text1 + selection + text2 + end;

        if (textarea.setSelectionRange)
        {
            if (selection.length == 0)
                textarea.setSelectionRange(newCursorPos + text1.length, newCursorPos + text1.length);
            else
                textarea.setSelectionRange(newCursorPos, newCursorPos + text1.length + selection.length + text2.length);
            textarea.focus();
        }
        textarea.scrollTop = scrollPos;
    }
    // Just put them on the end, then.
    else
    {
        textarea.value += text1 + text2;
        textarea.focus(textarea.value.length - 1);
    }
}

// Only allow form submission ONCE.
function submitonce(theform)
{
    smf_formSubmitted = true;
}

function submitThisOnce(form)
{
    // Hateful, hateful fix for Safari 1.3 beta.
    if (navigator.userAgent.indexOf('AppleWebKit') != -1)
        return !smf_formSubmitted;

    if (typeof(form.form) != "undefined")
        form = form.form;

    for (var i = 0; i < form.length; i++)
        if (typeof(form[i]) != "undefined" && form[i].tagName.toLowerCase() == "textarea")
            form[i].readOnly = true;

    return !smf_formSubmitted;
}

// Get the inner HTML of an element.
function getInnerHTML(element)
{
    if (typeof(element.innerHTML) != 'undefined')
        return element.innerHTML;
    else
    {
        var returnStr = '';
        for (var i = 0; i < element.childNodes.length; i++)
            returnStr += getOuterHTML(element.childNodes[i]);

        return returnStr;
    }
}

function getOuterHTML(node)
{
    if (typeof(node.outerHTML) != 'undefined')
        return node.outerHTML;

    var str = '';

    switch (node.nodeType)
    {
        // An element.
        case 1:
            str += '<' + node.nodeName;

            for (var i = 0; i < node.attributes.length; i++)
            {
                if (node.attributes[i].nodeValue != null)
                    str += ' ' + node.attributes[i].nodeName + '="' + node.attributes[i].nodeValue + '"';
            }

            if (node.childNodes.length == 0 && in_array(node.nodeName.toLowerCase(), ['hr', 'input', 'img', 'link', 'meta', 'br']))
                str += ' />';
            else
                str += '>' + getInnerHTML(node) + '</' + node.nodeName + '>';
            break;

        // 2 is an attribute.

        // Just some text..
        case 3:
            str += node.nodeValue;
            break;

        // A CDATA section.
        case 4:
            str += '<![CDATA' + '[' + node.nodeValue + ']' + ']>';
            break;

        // Entity reference..
        case 5:
            str += '&' + node.nodeName + ';';
            break;

        // 6 is an actual entity, 7 is a PI.

        // Comment.
        case 8:
            str += '<!--' + node.nodeValue + '-->';
            break;
    }

    return str;
}

// Checks for variable in theArray.
function in_array(variable, theArray)
{
    for (var i = 0; i < theArray.length; i++)
    {
        if (theArray[i] == variable)
            return true;
    }
    return false;
}

function saveEntities()
{
    var textFields = ["subject", "message", "guestname", "evtitle", "question"];
    for (i in textFields)
        if (document.forms.postmodify.elements[textFields[i]])
            document.forms.postmodify[textFields[i]].value = document.forms.postmodify[textFields[i]].value.replace(/&#/g, "&#38;#");
    for (var i = document.forms.postmodify.elements.length - 1; i >= 0; i--)
        if (document.forms.postmodify.elements[i].name.indexOf("options") == 0)
            document.forms.postmodify.elements[i].value = document.forms.postmodify.elements[i].value.replace(/&#/g, "&#38;#");
}
