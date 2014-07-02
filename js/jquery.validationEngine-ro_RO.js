

(function($) {
	$.fn.validationEngineLanguage = function() {};
	$.validationEngineLanguage = {
		newLang: function() {
			$.validationEngineLanguage.allRules = 	{"required":{    			// Add your regex rules here, you can take telephone as an example
						"regex":"none",
						"alertText":"* Acest camp este obligatoriu",
						"alertTextCheckboxMultiple":"* Va rugam sa selectati o optiune",
						"alertTextCheckboxe":"* Aceasta caseta este necesara"},
					"length":{
						"regex":"none",
						"alertText":"*Intre ",
						"alertText2":" and ",
						"alertText3": " caractere permise"},
					"maxCheckbox":{
						"regex":"none",
						"alertText":"* Controale permis depasita"},	
					"minCheckbox":{
						"regex":"none",
						"alertText":"* Va rugam sa selectati ",
						"alertText2":" optiuni"},	
					"confirm":{
						"regex":"none",
						"alertText":"* Domeniul dvs. nu este de potrivire"},		
					"telephone":{
						"regex":"/^[0-9\-\(\)\ ]+$/",
						"alertText":"* Numar de telefon nevalid"},	
					"email":{
						"regex":"/^[a-zA-Z0-9_\.\-]+\@([a-zA-Z0-9\-]+\.)+[a-zA-Z0-9]{2,4}$/",
						"alertText":"* Invalid e-mail"},	
					"date":{
                         "regex":"/^[0-9]{4}\-\[0-9]{1,2}\-\[0-9]{1,2}$/",
                         "alertText":"* Invalid data, trebuie sa fie in YYYY-MM-DD"},
					"onlyNumber":{
						"regex":"/^[0-9\ ]+$/",
						"alertText":"* Numai numere"},	
					"noSpecialCaracters":{
						"regex":"/^[0-9a-zA-Z]+$/",
						"alertText":"* Nu sunt permise caractere speciale"},	
					"ajaxUser":{
						"file":"validateUser.php",
						"extraData":"name=eric",
						"alertTextOk":"* Acest utilizator este disponibil",	
						"alertTextLoad":"* De incarcare, va rugam sa asteptati",
						"alertText":"* Acest utilizator este deja luat"},	
					"ajaxName":{
						"file":"validateUser.php",
						"alertText":"* Acest nume este deja luat",
						"alertTextOk":"* Acest nume este disponibil",	
						"alertTextLoad":"* De incarcare, va rugam sa asteptati"},		
					"onlyLetter":{
						"regex":"/^[a-zA-Z\ \']+$/",
						"alertText":"* Scrisori numai"},
					"validate2fields":{
    					"nname":"validate2fields",
    					"alertText":"* Trebuie sa aveti o prenumele si un Nume"}
					}
					
		}
	}
})(jQuery);

$(document).ready(function() {	
	$.validationEngineLanguage.newLang()
});