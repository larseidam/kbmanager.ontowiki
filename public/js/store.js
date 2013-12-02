/**
 * Javascript stuff for form action
 * 
 * @category   OntoWiki
 * @package    OntoWiki_extensions_formgenerator
 * @author     Lars Eidam <larseidam@googlemail.com>
 * @author     Konrad Abicht <konrad@inspirito.de>
 * @copyright  Copyright (c) 2011
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

function renewAllModel(mode) {
    var result = 0;
	$("#responseAll").html('<div class="storeProcessDiv storeWorkingDiv"></div>');
	$("#storeModelProcessArea").append('Start working to ' + mode + ' all Models\n');
	$.each(models, function(res){
        if ('namespaces' != res)
            result += renewModel(res, mode);
	});
    if (0 == result) {
        $("#responseAll").html('<div class="storeProcessDiv storeFinshDiv"></div>');
        $("#storeModelProcessArea").append('Finish working to ' + mode + ' all Models\n');
    }
    else {
        $("#responseAll").html('<div class="storeProcessDiv storeErrorDiv"></div>');
        $("#storeModelProcessArea").append('Error on ' + mode + ' all Models (Code: ' + result + ')\n');
    }
    
}
function renewModel(modelName, mode) {
    var result = 0;
    $("#response" + modelName).empty();
	$("#response" + modelName).html('<div class="storeProcessDiv storeWorkingDiv"></div>');
	$("#storeModelProcessArea").append('Start to ' + mode + ' Model ' + modelName + '\n');
	
	result += changeModel(modelName, mode);
	
    if (0 == result) {
        $("#response" + modelName).html('<div class="storeProcessDiv storeFinshDiv"></div>');
        $("#storeModelProcessArea").append('Finish to ' + mode + ' Model ' + modelName + '\n');
    }
    else {
        $("#response" + modelName).html('<div class="storeProcessDiv storeErrorDiv"></div>');
        $("#storeModelProcessArea").append('Error on ' + mode + ' Model ' + modelName + ' (Code: ' + result + ')\n');
    }
    
    return result;
}

function changeModel(modelName, mode) {
    var returnValue = 0;
	$.ajax({
        async:false,
        dataType: "json",
        type: "POST",
        data: {
            modelName: modelName,
            do: mode,
            hidden: $('#model' + modelName + 'Hidden').is(":checked") ? $('#model' + modelName + 'Hidden').val() : 'false',
            backup: $('#storeBackup').is(":checked") ? $('#storeBackup').val() : 'false'
        },
        context: $("#response" + modelName),
        url: 'changemodel/',
        // complete, no errors
        success: function ( res ) 
        {
            $(this).html('<div class="storeProcessDiv storeWorkingDiv">delete Model: ' + models[modelName].namespace + '</div>');
            try {
                if (undefined != res.error) {
                    $("#storeModelProcessArea").append('\tFailure: ' + res.error + '\n');
                    returnValue = -1;
                }
                else {
                    $.each(res.log, function(logEntry){
                        $("#storeModelProcessArea").append('\t' + res.log[logEntry] + '\n');
                    });
                    changeButtons(mode, modelName);
                }
            }
            catch (err) {
                returnValue = -2;
            }
        },
        
        error: function (jqXHR, textStatus, errorThrown)
        {
            console.log (jqXHR);
            console.log (textStatus);
            console.log (errorThrown);
            returnValue = -3;
        }
    });
    return returnValue;
}

function changeButtons(mode, modelName) {
    
    if ('create' == mode || 'add' == mode) {
        $('#model' + modelName + 'Create').addClass('storeDisabledButton');
        $('#model' + modelName + 'Fill').removeClass('storeDisabledButton');
        $('#model' + modelName + 'Add').addClass('storeDisabledButton');
        $('#model' + modelName + 'Renew').removeClass('storeDisabledButton');
        $('#model' + modelName + 'Clear').removeClass('storeDisabledButton');
        $('#model' + modelName + 'Delete').removeClass('storeDisabledButton');
        $('#model' + modelName + 'Backup').removeClass('storeDisabledButton');
        $('#model' + modelName + 'StatusA').removeClass('storeHidden');
        $('#model' + modelName + 'StatusNA').addClass('storeHidden');
        
    } else if ('delete' == mode) {
        $('#model' + modelName + 'Create').removeClass('storeDisabledButton');
        $('#model' + modelName + 'Fill').addClass('storeDisabledButton');
        $('#model' + modelName + 'Add').removeClass('storeDisabledButton');
        $('#model' + modelName + 'Renew').addClass('storeDisabledButton');
        $('#model' + modelName + 'Clear').addClass('storeDisabledButton');
        $('#model' + modelName + 'Delete').addClass('storeDisabledButton');
        $('#model' + modelName + 'Backup').addClass('storeDisabledButton');
        $('#model' + modelName + 'StatusA').addClass('storeHidden');
        $('#model' + modelName + 'StatusNA').removeClass('storeHidden');
    }
}
