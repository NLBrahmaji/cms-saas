export type AuthenticatedUser = {
  id: number;
  name: string;
  email: string;
};

export type UserEnvelope = {
  user: AuthenticatedUser;
};

export type RegisterPayload = {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
};

export type RegisteredAccount = {
  id: number;
  name: string;
  status: string;
};

export type RegisterResponse = {
  user: AuthenticatedUser;
  account: RegisteredAccount;
};
