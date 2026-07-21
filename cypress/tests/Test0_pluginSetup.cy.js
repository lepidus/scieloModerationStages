describe('SciELO Moderation Stages - Plugin setup', function () {
    it('Enables SciELO Moderation Stages plugin', function () {
        cy.login('dbarnes', null, 'publicknowledge');

        cy.get('nav').contains('Settings').click();
        cy.get('nav').contains('Website').click({ force: true });

        cy.waitJQuery();
        cy.get('button[id="plugins-button"]').click();

        cy.get('input[id^=select-cell-scielomoderationstagesplugin]').check();
        cy.get('input[id^=select-cell-scielomoderationstagesplugin]').should('be.checked');
    });
});
