**Inhaltsverzeichnis**

- [Das Shopware Migrations-Tool](#das-shopware-migrations-tool)
- [Grundlegende Funktionsweise](#grundlegende-funktionsweise)
- [1. Das Backend-Modul](#1-das-backend-modul)
- [2. Die Profile](#2-die-profile)
	- [Standard-Methoden für jedes Profile:](#standard-methoden-für-jedes-profile)
		- [Allgemein](#allgemein)
		- [Select-Methoden für das Mapping](#select-methoden-für-das-mapping)
		- [Select-Methoden für den Import](#select-methoden-für-den-import)
- [3. Migrationslogik](#3-migrationslogik)

# Das Shopware Migrations-Tool

** Diese Dokumentation wird noch bearbeitet **

Das Shopware Migrations-Tool dient dazu, Artikel, Kunden und Bestellungen aus fremden Shopsystemen nach Shopware zu migirieren. Die grundlegende Bedienung sowie einer Übersicht der migrierbaren Felder aus den jeweiligen Shopsystemen ist im offiziellen Shopware-Wiki dokumentiert: http://wiki.shopware.com/Plugin-Migration-von-anderen-Shopsystemen-zu-Shopware_detail_1011.html.

Zur Zeit unterstützt das Tool:

* Oxid bis 4.9.7
* Magento 1.7.1.0 bis 1.9.2.4
* xt:Commerce Veyton 4.0 bis 4.1
* Gambio GX bis 2.6.2.0
* xtModified & xt:Commerce bis 3.04
* Prestashop bis 1.6.1.4

Mit der Veröffentlichung des Migrations-Tools unter MIT soll die Möglichkeit geschaffen werden, Änderungen aus der Community schneller in das Tool zurück fließen zu lassen. Außerdem soll es erleichtert werden, das Tool um eigene Profile zu erweitern, um auch Shopsysteme anzubinden, für die es bisher keinen Migrationsweg gibt.


# Grundlegende Funktionsweise

Das Migrationstool besteht grundlegend aus 3 Komponenten:

1. Dem Backend-Modul, über das die Migration konfiguriert wird: Datenbankverbindungen, Mappings und die zu migrierenden Felder werden hier festgelegt.
2. Profile für die einzelnen Shopsysteme: Die jeweiligen Profile liefern für jede zu migrierende Ressource passend aufbereitete SQL-Queries zurück.
3. Der Migrations-Logik: Diese führt die von den jeweiligen Profilen zurück gegebenen Queries aus, iteriert die Results, bereitet diese auf und importiert diese mittels der Shopware API nach Shopware (zur Zeit wird hier noch die SW3-API genutzt, die in SW4 ebenfalls zur Verfügung steht).


# 1. Das Backend-Modul

Dokumentation folgt

# 2. Die Profile
Die einzelnen Migrations-Profile finden sich unter `Components/Migration/Profile`. Jedes der dort vorhandenen Profile erbt von der Basis-Klasse `Shopware\SwagMigration\Components\Migration\Profile` in `Components/Migration/Profile.php`.

Die `Profile.php` hat eine eigene Datenbankverbindung zu dem zu migrierenden Shopsystem (Quell-Shop). Sie enthält weiterhin eine Reihe vorgegebener Methoden wie bspw. **queryCategories**, die während der Migration aufgerufen werden. Diese Query-Methoden rufen jeweils wiederum die dazugehörige "get...Select"-Methode auf, bspw. **getCategorySelect**. Diese Select-Methoden müssen in den jeweiligen Profilen implementiert werden. Hierbei müssen die Select-Anweisungen so aufgebaut sein, dass sie dem von der Migrations-Logik vorgegebenen Format entsprechen - der Kategorie-Name muss bspw. als "description" selektiert werden.

## Standard-Methoden für jedes Profile:

Im Folgenden sollen kurz alle zur Verfügung stehenden Methoden besprochen werden, die während der Migrations ggf. gelesen werden. Hierbei gibt es einige Grundsätze zu beachten:

* Bei allen Selects, bei denen IDs selektiert werden (Benutzergruppen, Artikel u.v.m.), müssen die selben IDs auch selektiert werden, wenn die entsprechenden Entities an anderer Stelle referenziert werden. Sprich: Wird die Benutzergruppe "Shopkunden" mit der numerischen ID '1' selektiert, kann diese später beim Benutzer-Import nicht über die ID 'EK' referenziert werden.

### Allgemein
* getProductImagePath
    * Gibt den relativen Standard-Pfad zu den Produktbildern des Quellshops zurück.

### Select-Methoden für das Mapping

Nicht alle Selects werden genutzt, um die entsprechenden Entitäten direkt nach Shopware zu migrieren. Viele Selects dienen nur dazu, Mappings vom Quell-Shop zu den entsprechenden Shopware-Ressourcen zu erzeugen. So werden also bspw. Sprachen nicht importiert - es wird lediglich die ID der Sprache im Quellshop einer ID der Sprache im Shopware-Shop zugeordnet. Diese Zuordnung geschieht über das ExtJS-Backend-Modul (siehe o.g. Wiki-Artikel).


* getDefaultLanguageSelect
    * Query um die Standard-Sprache des Shops zu selektieren
    * Darf nur einen Eintrag selektieren.
* getLanguageSelect
    * Selektiert alle im Quell-Shop vorhanden Sprachen
    * Felder:
        * **id**: ID der Sprache.
        * **name**: Angezeigte Sprache für das Mapping
* getShopSelect
    * Selektiert alle (Sub)shops des Shopsystems
    * Felder:
        * **id**:
        * **name**: Name des Shops - wird im Mapping angezeigt
        * **url**: (Optional) URL zum Shop
* getCustomerGroupSelect
    * Selektiert alle Benutzergruppen des Quell-Shops
    * Felder:
        * **id**: Id der Benutzergruppe
        * **name**: Name der Benutzergruppe für die Anzeige im Mapping
* getPaymentMeanSelect
    * Selektiert alle Zahlungsarten
    * **id**: Id der Zahlungsart
    * **name**: Name der Zahlungsart (für die Anzeige im Mapping)
* getOrderStatusSelect
    * Selektiert alle Bestellstati
    * Felder:
        * **id**: Id der Bestellstati
        * **name**: Name des Bestellstatus fürs Mapping
* getTaxRateSelect
    * Selektiert alle Steuersätze
    * Felder:
        * **id**: Id des Steuersatzes
        * **name**: Steuerrate
* getAttributeSelect
    * Selektiert alle Artikel-Attribute. Hiermit sind zusätzliche Artikel-Eigenschaften gemeint, die in Shopware als Freitextfelder/Artikel-Attribute importiert werden sollen. Für Attribute im Sinne von "Variantenfähigkeit" stehen eigene Select-Methoden zur Verfügung.
    * Felder:
        * **id**: Id des Attributs
        * **name**: Attribut-Name
* getPropertyOptionSelect (optional)
    * Selektiert alle Eigenschafts-Optionen des Shops. Eigenschaften setzen sich in SW4 aus Gruppen (z.B. "Weine"), Optionen (z.B. "Geschmack") und Werten (z.B. "lieblich") zusammen. In vielen Shop-Systemen haben Eigenschaften aber nur Optionen und Werte. Ist dies der Fall, können im Mapping des Backend-Modules die Optionen des Quell-Shops Gruppen im Shopware-Shop zugeordnet werden. Dafür müssen an dieser Stelle die entsprechenden Optionen selektiert werden.
    * Felder:
        * **id**: Id der Eigenschaft
        * **name**: Name der Eigenschaft

### Select-Methoden für den Import

Folgende Select-Methoden haben jeweils die Aufgabe, für den Import bestimmter Entitäten passende Select zurück zu geben. Grundsätzlich gilt: Die Import-Logik bereitet diese Felder auf und importiert diese dann mit Hilfe der **alten** Shopware API. Bei Fragen zu importierbaren Feldern empfiehlt sich also auch immer ein Blick in die entsprechende Dokumentation: http://wiki.shopware.com/API-Import-Funktionen_detail_210.html

* getProductSelect
    * Selektiert alle Produkte für den Import
    * Felder:
        * **productID**:
        * **instock**:
        * **stockmin**:
        * **shippingtime**:
        * **net_price**:
        * **price**:
        * **baseprice**:
        * **releasedate**:
        * **added**:
        * **changed**:
        * **length**:
        * **width**:
        * **height**:
        * **supplier**:
        * **taxID**:
        * **active**:
        * **ean**:
        * **name**:
        * **description_long**:
        * **description**:
        * **meta_title**:
        * **meta_description**:
        * **keywords**:
    * Spezielle Felder für den Varianten-Import aus Systemen, in denen die Varianten physikalisch vorhanden sind (hierunter fällt beispielsweise Oxid). Diese Varianten können ohne große Aufbereitung importiert werden, durch die folgenden Felder werden die Optionen und der Parent-Artikel gekennzeichnet:
Bei Shops mit Attribut-System (XTC) werden diese Felder nicht benötigt.
        * **parentID**: Vater-Artikel. Muss vorher selektiert werden - hier ist also ggf. ein OrderBy nötig.
        * **additionaltext**: Mit Pipes separierte Optionen des Artikels, etwa "grün | XL"
        * **variant_group_names**: Mit Pipes separierte Gruppen des Artikels, etwa "Farbe | Größe"
        * **masterWithAttributes**: Falls der Parent-Artikel in dem Shop-System auch Varianten-Optionen hat, muss hier '1' selektiert werden. Andernfalls '0'.
* getAttributedProductsSelect:
    * Selektiert alle IDs von Produkten, die Attribute haben. Diese Methode wird nur benötigt, wenn der Quellshop Varianten in Form von Attributen implementiert, wie es bspw. bei XTC der Fall ist.
    * Felder:
        * **productID**: Id des Produktes, das Attribute hat
* getProductAttributesSelect($id)
    * Selektiert für eine gegebene ProduktId alle vorhandenen Attribute. Das Import-Skript wird dafür automatisch die passenden Konfiguratoren erzeugen und die nötigen Varianten generieren.
    * Felder:
        * **group_name**: Attribut-Gruppe. Bspw. "Farbe"
        * **productId**: Id des Produktes, das diese Kombination aus Gruppe/Option hat
        * **option_name**: Attribut-Name. Bspw. "grün"
        * **price**: Price-Aufschlag für dieses Attribut
* getProductsWithPropertiesSelect
    * Selektiert Produkte, die Eigenschaften (SW-Nomenklatur) haben.
    * Felder:
        * **productID**: Id der Produkte, die Eigenschaften haben
* getProductPropertiesSelect($id)
    * Selektiert alle Eigenschaften eines gegebenen Produktes.
    * Felder:
        * **productID**: Id des Produktes mit der Eigenschaft
        * **group**: Eigenschaften-Gruppe (z.B. Weine). Wird diese Feld nicht selektiert, wird beim Import geprüft, ob ein Mapping der Option auf eine SW-Gruppe vorgenommen wurde (vgl. getPropertyOptionSelect).
        * **option**: Eigenschaften-Option (z.B. Geschmack)
        * **value**: Eigenschaften-Wert (z.B. lieblich)
* getProductImageSelect
    * Selektiert alle Produktbilder
* getProductRatingSelect
    * Selektiert alle Produkt-Bewertungen
* getProductPriceSelect
    * Selektiert Produkt-Preise
* getOrderSelect
    * Selektiert Bestellungen
* getCustomerSelect
    * Selektiert Kunden
* getCategorySelect
    * Selektiert Kategorien

## Produktimport

Gerade Produkte sind in der Regel recht komplexe Entitäten, die in den verschiedenen Shopsystemen sehr unterschiedlich abgebildet werden. Gerade wenn es um die Abbildung von Varianten geht, sind die Shops in der Regel sehr unterschiedlich. Das Migrationsskript unterstützt daher verschiedene Möglichkeiten, Produkte und Produktvarianten zu importieren.

* Parent/Child
    * Liegen die Produkte und ihre Varianten in einer Parent/Child-Struktur vor (wie bspw. bei Oxid), dann können die Varianten sehr leicht importiert werden. In der getProductSelect-Methode können dann zusätzlich die Felder **parentId**, **additionaltext**, **variant_group_names** und **masterWithAttributes** selektiert werden. Eine genauere Beschreibung der Felder findet sich in der Dokumentation der Methode **getProductSelect**.
* Attribute
    * Liegen die Varianten in Form von Attributen vor (XTC, Gambio), können diese Attribute als Konfiguratoren importiert werden. Das Migrationstool wird dann automatisch die Variantengenerierung anstoßen.

# 3. Migrationslogik
