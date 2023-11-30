describe('SciELO Moderation Stages - Hide participants discussion', function () {
    it('Moderators participants are hidden when author opens discussion', function () {
        cy.login('fpaglieri', null, 'publicknowledge');
        
        cy.get('#archive-button').click();
        cy.contains('a','View').click();

        cy.contains('Discussions').click();
        cy.contains('Add discussion').click();
        cy.waitJQuery();

        cy.contains('label', 'Stephanie Berardo, Moderator').should('not.exist');
        cy.contains('label', 'David Buskins, Moderator').should('not.exist');
    });
    it('Participants are not hidden for other roles', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.get('#archive-button').click();
        cy.contains('View Paglieri').first().click();

        cy.contains('Add discussion').click();
        cy.waitJQuery();
        
        cy.contains('label', 'Stephanie Berardo, Moderator');
        cy.contains('label', 'David Buskins, Moderator');
    });
});