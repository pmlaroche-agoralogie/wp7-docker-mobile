chaque utilisateur voit dans son dashboard un module meteo, qui est le widget meteofrance.
C'est une iframe  de code :
<iframe id="widget_autocomplete_preview"  width="150" height="300" frameborder="0" src="https://meteofrance.com/widget/prevision/xxxxxx##3D6AA2" title="Prévisions Saint-Ambroix par Météo-France"> </iframe>
le code xxxxxx  , à 6 chiffres, est le code INSEE de la commune, suivi d'un 0
Par defaut, on affiche le widget avec le code 644300 (code pur Orthez).
A coté du widget, ou en bas de la page dashboard, on a un lien "personaliser la meteo", qui permet de saisir un code postal.
On fait alors un appel à https://geo.api.gouv.fr/communes?codePostal=yyyyy&fields=code,nom&format=json
avec yyyyy le coed postal.
et on obtient un retour json de type :
[
  { "nom": "Chamalières", "code": "63075" }
]
ce code est alors stocké dans la fiche users, et ce sera sa meteo pour toutes les connexions futures
