function loginAuthorUser() {
    cy.get('input[id=username]').clear();
    cy.get('input[id=username]').type(Cypress.env('OJSAuthorUsername'), { delay: 0 });
    cy.get('input[id=password]').type(Cypress.env('OJSAuthorPassword'), { delay: 0 });
    cy.get('button[class=submit]').click();
}

function loginAdminUser() {
    cy.get('input[id=username]').clear();
    cy.get('input[id=username]').type(Cypress.env('OJSAdminUsername'), { delay: 0 });
    cy.get('input[id=password]').type(Cypress.env('OJSAdminPassword'), { delay: 0 });
    cy.get('button[class=submit]').click();
}

function userLogout() {
    cy.get(".pkpDropdown.app__headerAction > .pkpButton").click();
    cy.get("a.pkpDropdown__action").contains("Logout").click();
}

function submissionStep1() {
    cy.get('#sectionId').select('1');
    cy.get('#pkp_submissionChecklist > ul > li > label > input').check();
    cy.get('#privacyConsent').check();

    cy.get('#submissionStep1 > .formButtons > .submitFormButton').click();
}

function submissionStep2() {
    cy.get('#submitStep2Form > .formButtons > .submitFormButton').click();
}

function submissionStep3() {
    cy.get('input[name^="title"]').first().type("Submission test first moderation stage", { delay: 0 });
    cy.get('label').contains('Title').click();
    cy.get('textarea[id^="abstract-en_US"]').type("Example of abstract");
    cy.get('.section > label:visible').first().click();
    cy.get('ul[id^="en_US-keywords-"]').then(node => {
        node.tagit('createTag', "Dummy keyword");
    });
    cy.get('#submitStep3Form > .formButtons > .submitFormButton').click();
}

function submissionStep4() {
    cy.get('#submitStep4Form > .formButtons > .submitFormButton').click();
    cy.get('.pkp_modal_confirmation > .footer > .ok').click();
}

describe("SciELO Moderation Stages Plugin - Exhibitor of submissions' stage in submissions listing page", function() {
    it("Author user submits", function() {
        cy.visit(Cypress.env('baseUrl') + 'index.php/scielo/submissions');
        loginAuthorUser();

        cy.get('.pkpHeader__actions:visible > a.pkpButton').click();
        submissionStep1();
        submissionStep2();
        submissionStep3();
        submissionStep4();
        userLogout();
    });
    it("Check if exhibitor appears in submissions listing", function() {
        loginAdminUser();
        cy.get("#active-button").click();
        cy.get(".listPanel__itemIdentity--submission:visible > .listPanel__itemModerationStage").first().contains("Moderation stage: Format Pre-Moderation");        
    });
});