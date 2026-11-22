export type Post = {
    id: string;
    title: string;
    published_at: string;
    date: string;
    url: string;
    html: string;
    tags: string[];
};

export type PostYear = {
    year: number;
    count: number;
    url: string;
};
