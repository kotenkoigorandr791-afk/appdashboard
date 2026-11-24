package orbit.nex.gitea.helpers.codeeditor.theme;

import android.content.Context;
import androidx.annotation.ColorRes;
import orbit.nex.gitea.R;
import orbit.nex.gitea.helpers.AppDatabaseSettings;
import orbit.nex.gitea.helpers.AppUtil;
import orbit.nex.gitea.helpers.codeeditor.languages.LanguageElement;

/**
 * @author qwerty287
 */
public interface Theme {

	FiveColorsTheme FIVE_COLORS = new FiveColorsTheme();
	FiveColorsDarkTheme FIVE_COLORS_DARK = new FiveColorsDarkTheme();
	BlueMoonTheme BLUE_MOON_THEME = new BlueMoonTheme();
	BlueMoonDarkTheme BLUE_MOON_DARK_THEME = new BlueMoonDarkTheme();

	static Theme getDefaultTheme(Context context) {
		if (Integer.parseInt(
						AppDatabaseSettings.getSettingsValue(
								context, AppDatabaseSettings.APP_CE_SYNTAX_HIGHLIGHT_KEY))
				== 0) {
			return AppUtil.getColorFromAttribute(context, R.attr.isDark) == 1
					? FIVE_COLORS_DARK
					: FIVE_COLORS;
		} else {
			return AppUtil.getColorFromAttribute(context, R.attr.isDark) == 1
					? BLUE_MOON_DARK_THEME
					: BLUE_MOON_THEME;
		}
	}

	@ColorRes
	int getColor(LanguageElement element);

	@ColorRes
	int getDefaultColor();

	@ColorRes
	int getBackgroundColor();
}
