class IBANValidator {
    constructor() {
        this.countryLengths = {
            BE: 16, DE: 22, FR: 27, IT: 27, ES: 24, NL: 18, PT: 25,
            AT: 20, CH: 21, DK: 18, FI: 18, GB: 22, IE: 22, LU: 20,
            MC: 27, SE: 24, EE: 20, LV: 21, LT: 20, SK: 24, CZ: 24,
            HU: 28, PL: 28, BG: 22, RO: 24, HR: 21, SI: 19, IS: 26,
            NO: 15, LI: 21, MT: 31, CY: 28, SM: 27, VA: 22, AD: 24,
            GI: 23, FO: 18, GL: 18, AL: 28, MK: 19, BA: 20, MD: 24,
            TN: 24, TR: 26, AE: 23, SA: 24, QA: 29, KW: 30, IL: 23,
            BY: 28, UA: 29, XK: 20
        };
        this.countryNames = {
            BE: 'Belgique/België', DE: 'Allemagne', FR: 'France',
            ES: 'Espagne', IT: 'Italie', NL: 'Pays-Bas', PT: 'Portugal',
            AT: 'Autriche', CH: 'Suisse', DK: 'Danemark', FI: 'Finlande',
            GB: 'Royaume-Uni', IE: 'Irlande', LU: 'Luxembourg',
            MC: 'Monaco', SE: 'Suède', EE: 'Estonie', LV: 'Lettonie',
            LT: 'Lituanie', SK: 'Slovaquie', CZ: 'République tchèque',
            HU: 'Hongrie', PL: 'Pologne', BG: 'Bulgarie', RO: 'Roumanie',
            HR: 'Croatie', SI: 'Slovénie', IS: 'Islande', NO: 'Norvège',
            LI: 'Liechtenstein', MT: 'Malte', CY: 'Chypre', SM: 'Saint-Marin',
            VA: 'Vatican', AD: 'Andorre', GI: 'Gibraltar', FO: 'Îles Féroé',
            GL: 'Groenland', AL: 'Albanie', MK: 'Macédoine du Nord',
            BA: 'Bosnie-Herzégovine', MD: 'Moldavie', TN: 'Tunisie',
            TR: 'Turquie', AE: 'Émirats arabes unis', SA: 'Arabie saoudite',
            QA: 'Qatar', KW: 'Koweït', IL: 'Israël', BY: 'Biélorussie',
            UA: 'Ukraine', XK: 'Kosovo'
        };
    }

    format(iban) {
        return (iban || '').replace(/\s+/g, '').toUpperCase().replace(/(.{4})/g, '$1 ').trim();
    }

    validate(iban) {
        if (!iban) return { valid: false, error: 'IBAN requis' };
        const clean = iban.replace(/\s+/g, '').toUpperCase();
        if (clean.length < 15) return { valid: false, error: 'IBAN trop court' };
        const country = clean.substring(0, 2);
        const expectedLen = this.countryLengths[country];
        if (expectedLen && clean.length !== expectedLen) {
            return { valid: false, error: `Longueur incorrecte pour ${country} (attendu: ${expectedLen}, reçu: ${clean.length})` };
        }
        const rearranged = clean.substring(4) + clean.substring(0, 4);
        let numeric = '';
        for (const char of rearranged) {
            if (char >= 'A' && char <= 'Z') numeric += (char.charCodeAt(0) - 55);
            else numeric += char;
        }
        let remainder = 0;
        for (let i = 0; i < numeric.length; i++) {
            remainder = (remainder * 10 + parseInt(numeric[i])) % 97;
        }
        if (remainder !== 1) return { valid: false, error: `IBAN invalide (somme de contrôle: ${remainder}, attendu: 1)` };
        const countryName = this.countryNames[country];
        if (countryName) return { valid: true, country: countryName };
        return { valid: true, country: country };
    }
}

export default IBANValidator;
