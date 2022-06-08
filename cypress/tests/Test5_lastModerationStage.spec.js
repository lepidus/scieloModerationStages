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

function sendSubmissionToNextStage() {
    cy.get("a").contains("Assign").click();
    cy.get('tr[id^="component-grid-users-userselect-userselectgrid-row"] > .first_column > input').first().click();
    cy.get('input[name="sendNextStage"][value="1"]').click();
    cy.get("#addParticipantForm > .formButtons > .submitFormButton").click();
    cy.wait(3000);
}

function checkOptionSendNextStageIsNotPresent() {
    cy.get("a:visible").contains("Assign").click();
    cy.contains("This submission is in the Format Pre-Moderation stage, do you want to send it to the Content Pre-Moderation stage?").should('not.exist');
}

describe("SciELO Moderation Stages Plugin - Option to sent submission to next moderation stage when submission in last stage", function() {
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
    it("Check option to sent submission to next moderation stage does not show when submision is in last moderation stage", function() {
        loginAdminUser();
        cy.get("#active-button").click();
        cy.get(".listPanel__itemActions:visible > a.pkpButton").first().click();
        
        sendSubmissionToNextStage();
        sendSubmissionToNextStage();
        checkOptionSendNextStageIsNotPresent();
    });
});