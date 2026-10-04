export type AuthenticatedUser = {
  id: number;
  name: string;
  email: string;
};

export type UserEnvelope = {
  user: AuthenticatedUser;
};
