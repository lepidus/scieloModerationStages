import '../support/commands.js';

describe('SciELO Moderation Stages - Features on submissions page', function () {
    const submission1 = 'Night of the Living Dead';
    const submission3 = 'Ju-on: The Grudge';
    
    before(function() {
        Cypress.config('defaultCommandTimeout', 10000);
    });

    it("Authors can only view submissions' moderation stage", function () {
        cy.login('fpaglieri', null, 'publicknowledge');
        cy.findSubmission('myQueue', submission1);

        cy.get('[data-cy="active-modal"]').within(() => {
            cy.contains('Moderation stage:');
            cy.contains('Area Moderation');
            cy.get('[data-cy="moderationStageCell"]').should('not.exist');
        });
    });
    it("Editor can view submissions' moderation stage", function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('active', submission3);

        cy.get('[data-cy="active-modal"]').within(() => {
            cy.contains('Moderation stage:');
            cy.contains('Manuscript Type Pre-Moderation');
        });
    });
    it("Editor can browse submissions by moderation stage", function () {
        cy.login('dbarnes', null, 'publicknowledge');

        cy.get('nav').contains('Area Moderation').click();
        cy.waitJQuery();
        cy.contains('table tr', submission1);
        cy.contains('table tr', submission3).should('not.exist');

        cy.get('nav').contains('Manuscript Type Pre-Moderation').click();
        cy.waitJQuery();
        cy.contains('table tr', submission3);
        cy.contains('table tr', submission1).should('not.exist');
    });
});
