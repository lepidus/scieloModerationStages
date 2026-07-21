function beginSubmission(submissionData) {
    cy.get('label:contains("English")').click();
    cy.setTinyMceContent('startSubmission-title-control', submissionData.title);
    cy.get('input[name="submissionRequirements"]').check();
    cy.get('input[name="privacyConsent"]').check();

    cy.contains('button', 'Begin Submission').click();
}

function detailsStep(submissionData) {
    cy.setTinyMceContent('titleAbstract-abstract-control-en', submissionData.abstract);
    submissionData.keywords.forEach(keyword => {
        cy.get('#titleAbstract-keywords-control-en').type(keyword, {delay: 0});
        cy.get('#titleAbstract-keywords-control-en').type('{enter}', {delay: 0});
    });

    cy.contains('button', 'Continue').click();
}

function filesStep(submissionData) {
    cy.addSubmissionGalleys(submissionData.files);
    cy.contains('button', 'Continue').click();
}

function contributorsStep(submissionData) {
    submissionData.contributors.forEach(authorData => {
        cy.contains('button', 'Add Contributor').click();
        cy.wait(1000);
        cy.get('div[role=dialog]:contains("Add Contributor")').within(() => {
            cy.get('input[name="givenName-en"]').type(authorData.given, {delay: 0});
            cy.get('input[name="familyName-en"]').type(authorData.family, {delay: 0});
            cy.get('input[name="email"]').type(authorData.email, {delay: 0});
            cy.get('select[name="country"]').select(authorData.country);
            cy.contains('button', 'Save').click();
        });
        cy.waitJQuery();
    });

    cy.contains('button', 'Continue').click();
}

Cypress.Commands.add('createSubmission', function (submissionData) {
    cy.get('a:contains("New Submission")').first().click();

    beginSubmission(submissionData);
    detailsStep(submissionData);
    filesStep(submissionData);
    contributorsStep(submissionData);
    cy.get('input[name="relationStatus"][value="1"]').check();
    cy.contains('button', 'Continue').click();
    cy.contains('button', 'Submit').click();
    cy.get('[data-cy="dialog"]').within(() => {
        cy.contains('button', 'Submit').click();
    });

    cy.waitJQuery();
    cy.contains('h1', 'Submission complete');
});

Cypress.Commands.add('findSubmission', function(view, title) {
    const viewNames = {
        myQueue: 'My Submissions as Author',
        active: 'Active submissions',
        archive: 'Published',
    };
    const viewName = viewNames[view] || view;

    cy.get('nav').contains(viewName).click();
    cy.contains('table tr', title)
        .contains('button', /^\s*View\s*$/)
        .scrollIntoView()
        .should('be.visible')
        .click({force: true});
});
