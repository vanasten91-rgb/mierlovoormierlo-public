# MvM Platform

Gedeelde applicatielaag voor de nieuwe publieke diensten van Mierlo voor Mierlo.

- API namespace: `mvm/v1`
- PHP: 8.4
- WordPress: 7.1+
- Beheerrechten zijn smalle MvM-capabilities; de plugin verleent geen brede wp-adminrechten aan redactionele rollen.
- Featuremodules worden afzonderlijk gebouwd en getest: Marktplaats (#35), REST/PWA (#36), Nieuwsbrief (#37).
- Geen productiecredentials of secrets in deze plugin.
