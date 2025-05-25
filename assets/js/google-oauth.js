/**
 * Google OAuth Client-Side JavaScript
 * 
 * This file handles the client-side of the Google OAuth flow.
 */

/**
 * Start the Google login process
 */
function startGoogleLogin() {
    // Get the Google Auth URL from our backend
    fetch('includes/google-oauth.php?action=get_auth_url')
        .then(response => response.json())
        .then(data => {
            if (data.auth_url) {
                // Redirect to Google's OAuth consent screen
                window.location.href = data.auth_url;
            } else {
                console.error('Failed to get Google auth URL:', data.error || 'Unknown error');
                alert('Помилка при спробі входу через Google. Спробуйте пізніше.');
            }
        })
        .catch(error => {
            console.error('Error starting Google login:', error);
            alert('Помилка з\'єднання з сервером. Спробуйте пізніше.');
        });
}

/**
 * Alternative implementation using direct auth URL construction
 * This is used if the fetch approach doesn't work in your environment
 */
function startGoogleLoginDirect() {
    // Get client ID from meta tag
    const clientId = document.querySelector('meta[name="google-signin-client_id"]').content;
    
    // Check if we have a client ID
    if (!clientId) {
        console.error('Google Client ID not found');
        alert('Помилка конфігурації Google входу. Зверніться до адміністратора.');
        return;
    }
    
    // Construct redirect URI (same domain, google-callback.php)
    const redirectUri = window.location.origin + '/mindflow/google-callback.php';
    
    // Define scope (what information we want to access)
    const scope = encodeURIComponent('https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email');
    
    // Build the authorization URL
    const authUrl = 'https://accounts.google.com/o/oauth2/auth?' +
        'client_id=' + encodeURIComponent(clientId) +
        '&redirect_uri=' + encodeURIComponent(redirectUri) +
        '&response_type=code' +
        '&scope=' + scope +
        '&access_type=online' +
        '&prompt=select_account';
    
    // Redirect to Google's OAuth consent screen
    window.location.href = authUrl;
}

/**
 * Display error messages if present in URL parameters
 */
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const error = urlParams.get('error');
    
    if (error) {
        let errorMessage = 'Сталася помилка під час авторизації.';
        
        switch (error) {
            case 'google_oauth':
                const oauthError = sessionStorage.getItem('google_oauth_error') || '';
                errorMessage = 'Помилка авторизації Google: ' + (oauthError || 'Спробуйте ще раз.');
                break;
            case 'google_oauth_failed':
                errorMessage = 'Не вдалося виконати вхід через Google. Спробуйте ще раз.';
                break;
            case 'no_code':
                errorMessage = 'Відсутній код авторизації. Спробуйте ще раз.';
                break;
            case 'user_cancelled':
                errorMessage = 'Авторизацію скасовано.';
                break;
        }
        
        // Create error message element
        const errorElement = document.createElement('div');
        errorElement.className = 'message error';
        errorElement.textContent = errorMessage;
        
        // Insert at the top of the container
        const container = document.querySelector('.auth-container');
        if (container) {
            // Check if there's already a message
            const existingMessage = container.querySelector('.message');
            if (existingMessage) {
                existingMessage.textContent = errorMessage;
                existingMessage.className = 'message error';
            } else {
                // Insert after h1
                const heading = container.querySelector('h1');
                if (heading) {
                    heading.insertAdjacentElement('afterend', errorElement);
                } else {
                    container.prepend(errorElement);
                }
            }
        }
    }
});