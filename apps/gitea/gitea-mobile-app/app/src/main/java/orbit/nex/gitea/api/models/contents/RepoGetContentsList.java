package orbit.nex.gitea.api.models.contents;

import com.google.gson.annotations.SerializedName;
import java.io.Serial;
import java.io.Serializable;
import java.text.ParseException;
import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.Locale;

public class RepoGetContentsList implements Serializable {

	@Serial private static final long serialVersionUID = 1L;

	@SerializedName("last_commit_when")
	private String lastCommitWhen;

	@SerializedName("last_committer_date")
	private Date lastCommitterDate;

	@SerializedName("last_author_date")
	private Date lastAuthorDate;

	@SerializedName("submodule_git_url")
	private String submoduleGitUrl;

	@SerializedName("_links")
	private Links links;

	@SerializedName("last_commit_sha")
	private String lastCommitSha;

	@SerializedName("type")
	private String type;

	@SerializedName("encoding")
	private String encoding;

	@SerializedName("sha")
	private String sha;

	@SerializedName("content")
	private String content;

	@SerializedName("url")
	private String url;

	@SerializedName("target")
	private String target;

	@SerializedName("path")
	private String path;

	@SerializedName("size")
	private int size;

	@SerializedName("html_url")
	private String htmlUrl;

	@SerializedName("name")
	private String name;

	@SerializedName("download_url")
	private String downloadUrl;

	@SerializedName("git_url")
	private String gitUrl;

	public String getLastCommitWhen() {
		return lastCommitWhen;
	}

	public Date getLastCommiterDate() {
		return lastCommitterDate;
	}

	public Date getLastAuthorDate() {
		return lastAuthorDate;
	}

	public String getSubmoduleGitUrl() {
		return submoduleGitUrl;
	}

	public Links getLinks() {
		return links;
	}

	public String getLastCommitSha() {
		return lastCommitSha;
	}

	public String getType() {
		return type;
	}

	public String getEncoding() {
		return encoding;
	}

	public String getSha() {
		return sha;
	}

	public String getContent() {
		return content;
	}

	public String getUrl() {
		return url;
	}

	public String getTarget() {
		return target;
	}

	public String getPath() {
		return path;
	}

	public int getSize() {
		return size;
	}

	public String getHtmlUrl() {
		return htmlUrl;
	}

	public String getName() {
		return name;
	}

	public String getDownloadUrl() {
		return downloadUrl;
	}

	public String getGitUrl() {
		return gitUrl;
	}

	public Date getCompatibleCommitDate() {
		return lastCommitterDate;
	}
}
