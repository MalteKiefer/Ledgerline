// Classic content script (content scripts can't be ES modules directly), so it
// dynamically imports the real, bundled module from web_accessible_resources.
import(chrome.runtime.getURL('dist/content.js'));
