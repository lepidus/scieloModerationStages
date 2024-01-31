import '../support/commands.js';

describe('SciELO Moderation Stages - Hide participants discussion', function () {
    let submissionTitle = 'Candyman';
    
    it('Moderators participants are hidden when author opens discussion', function () {
        cy.login('fpaglieri', null, 'publicknowledge');
        cy.findSubmission('archive', submissionTitle);

        cy.contains('Discussions').click();
        cy.contains('Add discussion').click();
        cy.waitJQuery();

        cy.contains('label', 'Stephanie Berardo, Moderator').should('not.exist');
        cy.contains('label', 'David Buskins, Moderator').should('not.exist');
    });
    it('Participants are not hidden for other roles', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('archive', submissionTitle);

        cy.contains('Add discussion').click();
        cy.waitJQuery();
        
        cy.contains('label', 'Stephanie Berardo, Moderator');
        cy.contains('label', 'David Buskins, Moderator');
    });
});