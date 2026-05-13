class EPCGenerator {
    constructor() {
        this.SERVICE_TAG = 'BCD';
        this.VERSION = '002';
        this.CHARSET = '1'; // UTF-8
        this.IDENTIFICATION = 'SCT'; // SEPA Credit Transfer
    }

    sanitize(input) {
        if (!input) return '';
        return input.replace(/[\n\r\t]/g, ' ').trim();
    }

    generate(data) {
        const beneficiary = this.sanitize(data.beneficiary || '');
        const iban = (data.iban || '').replace(/\s+/g, '').toUpperCase();
        const amount = data.amount ? parseFloat(data.amount).toFixed(2) : '';
        const remittance = this.sanitize(data.remittance || '');

        if (!beneficiary) throw new Error('Beneficiary name is required');
        if (!iban) throw new Error('IBAN is required');

        const amountClean = amount ? amount.replace(/^EUR\s*/i, '') : '';

        const lines = [
            this.SERVICE_TAG,
            this.VERSION,
            this.CHARSET,
            this.IDENTIFICATION,
            '',
            beneficiary,
            iban,
            amountClean ? `${amountClean} EUR` : '',
            '',
            remittance,
            '',
        ];

        return lines.join('\n');
    }
}

export default EPCGenerator;
