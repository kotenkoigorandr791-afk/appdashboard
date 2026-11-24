package orbit.nex.gitea.helpers.attachments;

import android.net.Uri;

public class AttachmentsModel {

	private Uri uri;
	private String fileName;
	private long fileSize;

	public AttachmentsModel(String fileName, Uri uri) {
		this.fileName = fileName;
		this.uri = uri;
	}

	public Uri getUri() {
		return uri;
	}

	public void setUri(Uri uri) {
		this.uri = uri;
	}

	public String getFileName() {
		return fileName;
	}

	public void setFileName(String fileName) {
		this.fileName = fileName;
	}

	public long getFileSize() {
		return fileSize;
	}

	public void setFileSize(long fileSize) {
		this.fileSize = fileSize;
	}
}
