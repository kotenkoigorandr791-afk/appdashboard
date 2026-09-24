import ace from 'ace-builds';
import mode from 'ace-builds/src-noconflict/mode-c_cpp?url';
import theme from 'ace-builds/src-noconflict/theme-gruvbox?url';
import themeTomorrow from 'ace-builds/src-noconflict/theme-tomorrow?url';
import beautify from 'ace-builds/src-noconflict/ext-beautify?url';
import 'ace-builds/src-noconflict/ext-language_tools';

export const aceThemeDark = 'gruvbox';
export const aceThemeLight = 'tomorrow';

export function currentAceTheme(): string {
    const t = document.documentElement.getAttribute('data-theme');
    return t === 'lopaka-light' ? aceThemeLight : aceThemeDark;
}

export const aceMode = 'c_cpp';

ace.config.setModuleUrl(`ace/theme/${aceThemeDark}`, theme);
ace.config.setModuleUrl(`ace/theme/${aceThemeLight}`, themeTomorrow);
ace.config.setModuleUrl(`ace/mode/${aceMode}`, mode);
ace.config.setModuleUrl('ace/ext/beautify', beautify);
ace.require('ace/ext/language_tools');
ace.require('ace/ext/beautify');
