describe('SciELO Moderation Stages - Plugin setup', function () {
    it('Enables SciELO Moderation Stages plugin', function () {
		cy.login('dbarnes', null, 'publicknowledge');

		cy.contains('a', 'Website').click();

		cy.waitJQuery();
		cy.get('#plugins-button').click();

		cy.get('input[id^=select-cell-scielomoderationstagesplugin]').check();
		cy.get('input[id^=select-cell-scielomoderationstagesplugin]').should('be.checked');
    });
});